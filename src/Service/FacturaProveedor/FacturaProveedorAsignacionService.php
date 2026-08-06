<?php

namespace App\Service\FacturaProveedor;

use App\Entity\FacturaProveedor;
use App\Entity\FacturaProveedorLinea;
use App\Entity\FacturaProveedorLineaAsignacion;
use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Entity\StockMovimiento;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ProyectoRepository;
use App\Service\ProyectoGasto\ProyectoGastoService;



class FacturaProveedorAsignacionService
{
    public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly ProyectoRepository $proyectoRepository,
    private readonly ProyectoGastoService $proyectoGastoService
    ) {
    }

    public function procesar(
        FacturaProveedor $factura,
        array $lineasPost
    ): void {
        foreach ($factura->getLineas() as $linea) {

            // Solo procesamos líneas que todavía admiten asignaciones
            if (!in_array($linea->getEstado(), ['pendiente', 'parcial'], true)) {
                continue;
            }

            $lineaId = $linea->getId();

            // Esta línea no viene en el formulario
            if (!isset($lineasPost[$lineaId])) {
                continue;
            }

            $datos = $lineasPost[$lineaId];

            $tipoDestino = $datos['tipo_destino'] ?? null;
            $proyectoId = $datos['proyecto_id'] ?? null;
            $cantidadAsignada = $this->toFloat(
                $datos['cantidad_asignada'] ?? 0
            );

            if (!$tipoDestino || $cantidadAsignada <= 0) {
                continue;
            }

            /*
            * =========================
            * CANTIDAD DISPONIBLE
            * =========================
            */

            $cantidadLinea = max(
                (float) $linea->getCantidad(),
                1
            );

            $cantidadYaAsignada = 0.0;

            foreach ($linea->getAsignaciones() as $asignacionExistente) {
                $cantidadYaAsignada +=
                    (float) $asignacionExistente->getCantidad();
            }

            $cantidadDisponible = max(
                $cantidadLinea - $cantidadYaAsignada,
                0
            );

            if ($cantidadDisponible <= 0) {
                $linea->setEstado('asignada');
                continue;
            }

            // Nunca permitimos asignar más de lo disponible
            $cantidadAsignada = min(
                $cantidadAsignada,
                $cantidadDisponible
            );

            /*
            * =========================
            * IMPORTE ASIGNADO
            * =========================
            */

            $totalLinea = (float) $linea->getTotal();

            $precioUnidad = $totalLinea / $cantidadLinea;

            $importeAsignado = round(
                $precioUnidad * $cantidadAsignada,
                2
            );

            /*
            * =========================
            * CREAR ASIGNACIÓN
            * =========================
            */

            $asignacion = new FacturaProveedorLineaAsignacion();

            $asignacion->setLinea($linea);
            $asignacion->setCantidad($cantidadAsignada);
            $asignacion->setImporte(
                number_format($importeAsignado, 2, '.', '')
            );
            $asignacion->setTipoDestino($tipoDestino);
            $asignacion->setEstado('aplicada');

            /*
            * =========================
            * PROCESAR DESTINO
            * =========================
            */

            switch ($tipoDestino) {

                case 'obra':

                    if (!$proyectoId) {
                        continue 2;
                    }

                    $proyecto = $this->proyectoRepository->find(
                        $proyectoId
                    );

                    if (!$proyecto) {
                        continue 2;
                    }

                    $this->asignarAObra(
                        factura: $factura,
                        linea: $linea,
                        asignacion: $asignacion,
                        proyecto: $proyecto,
                        cantidadAsignada: $cantidadAsignada,
                        cantidadLinea: $cantidadLinea,
                        importeAsignado: $importeAsignado
                    );

                    break;

                case 'stock':



                    $this->asignarAStock(
                        factura: $factura,
                        linea: $linea,
                        asignacion: $asignacion,
                        cantidadAsignada: $cantidadAsignada
                    );


                    break;

                default:
                    continue 2;
            }

            /*
            * =========================
            * GUARDAR ASIGNACIÓN
            * =========================
            */


            $this->em->persist($asignacion);


            /*
            * =========================
            * ESTADO DE LA LÍNEA
            * =========================
            */

            $nuevaCantidadAsignada =
                $cantidadYaAsignada + $cantidadAsignada;

            if ($nuevaCantidadAsignada >= $cantidadLinea) {
                $linea->setEstado('asignada');
            } else {
                $linea->setEstado('parcial');
            }
        }

        /*
        * =========================
        * ESTADO GLOBAL FACTURA
        * =========================
        */

        $factura->setEstadoAsignacion(
            $this->calcularEstadoAsignacionFactura($factura)
        );
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }    

    private function asignarAObra(
        FacturaProveedor $factura,
        FacturaProveedorLinea $linea,
        FacturaProveedorLineaAsignacion $asignacion,
        Proyecto $proyecto,
        float $cantidadAsignada,
        float $cantidadLinea,
        float $importeAsignado,
        ?ProyectoGasto $gastoExistente = null
    ): void {
        if ($gastoExistente) {

            // Vinculamos la asignación al gasto ya previsto
            $asignacion->setProyecto($proyecto);
            $asignacion->setProyectoGasto($gastoExistente);

            // Confirmamos el gasto con el importe real de la factura
            $gastoExistente->setEstado('confirmado');
            $gastoExistente->setImportePrevisto(
                number_format($importeAsignado, 2, '.', '')
            );

            $gastoExistente->setFechaPrevista(
                $factura->getFechaFactura() ?: new \DateTime()
            );

            $gastoExistente->setProveedor(
                $factura->getProveedorNombre()
            );

            // Actualizamos el forecast ya existente
            $forecast = $gastoExistente->getForecast();

            if ($forecast) {
                $forecast->setImporteFr($importeAsignado * -1);

                $forecast->setFechaFr(
                    $factura->getFechaFactura() ?: new \DateTime()
                );
            }

            $gastoExistente->setNotas(
                trim(
                    ($gastoExistente->getNotas() ? $gastoExistente->getNotas() . "\n" : '') .
                    'Confirmado desde factura proveedor ' .
                    ($factura->getNumeroFactura() ?: 'sin número') .
                    ' · Línea: ' .
                    ($linea->getDescripcion() ?: '-') .
                    ' · Cantidad asignada: ' .
                    $cantidadAsignada .
                    ' de ' .
                    $cantidadLinea
                )
            );

            return;
        }

        // Si no se ha seleccionado gasto existente,
        // creamos uno nuevo como hasta ahora
        $gasto = $this->proyectoGastoService->crearDesdeFacturaProveedor(
            proyecto: $proyecto,
            factura: $factura,
            linea: $linea,
            importeAsignado: $importeAsignado,
            cantidadAsignada: $cantidadAsignada,
            cantidadLinea: $cantidadLinea
        );

        $asignacion->setProyecto($proyecto);
        $asignacion->setProyectoGasto($gasto);
    }

    private function asignarAStock(
        FacturaProveedor $factura,
        FacturaProveedorLinea $linea,
        FacturaProveedorLineaAsignacion $asignacion,
        float $cantidadAsignada
    ): void {
        $stockMovimiento = new StockMovimiento();

        $stockMovimiento->setTipoMovimiento(
            StockMovimiento::TIPO_ENTRADA_FACTURA
        );

        $stockMovimiento->setCantidad($cantidadAsignada);

        $stockMovimiento->setFecha(
            $factura->getFechaFactura() ?: new \DateTime()
        );

        // Relación con la asignación para poder llegar
        // posteriormente a la factura de proveedor
        $stockMovimiento->setFacturaProveedorLineaAsignacion(
            $asignacion
        );

        // Por ahora no vinculamos obligatoriamente con catálogo
        $stockMovimiento->setProducto(null);

        // Identificación del producto según factura proveedor
        $stockMovimiento->setDescripcionProducto(
            $linea->getDescripcion() ?: 'Producto sin descripción'
        );

        $stockMovimiento->setReferenciaProveedor(null);

        /*
        * =========================
        * DATOS FISCALES DE LA LÍNEA
        * =========================
        */

        $cantidadLinea = max(
            (float) $linea->getCantidad(),
            1
        );

        $baseTotalLinea = round(
            (float) ($linea->getImporteBruto() ?? 0),
            2
        );

        $totalLinea = round(
            (float) ($linea->getTotal() ?? 0),
            2
        );

        $ivaPct = (float) (
            $linea->getPorcentajeIva() ?? 0
        );

        $tieneRe = $linea->isTieneRecargoEquivalencia();

        $rePct = $tieneRe
            ? (float) (
                $linea->getPorcentajeRecargoEquivalencia() ?? 0
            )
            : 0.0;

        /*
        * =========================
        * IMPORTES TOTALES
        * =========================
        */

        $ivaTotalLinea = round(
            $baseTotalLinea * $ivaPct / 100,
            2
        );

        $reTotalLinea = round(
            $baseTotalLinea * $rePct / 100,
            2
        );

        $totalCalculadoLinea = round(
            $baseTotalLinea
            + $ivaTotalLinea
            + $reTotalLinea,
            2
        );

        /*
        * Si el total OCR/cuadrado de la línea está suficientemente
        * cerca del calculado, usamos el total real de la línea
        * como verdad final.
        */
        if (
            $totalLinea > 0
            && abs($totalLinea - $totalCalculadoLinea) <= 0.05
        ) {
            $totalCalculadoLinea = $totalLinea;
        }

        /*
        * =========================
        * IMPORTES UNITARIOS
        * =========================
        */

        $baseUnitaria = round(
            $baseTotalLinea / $cantidadLinea,
            2
        );

        $ivaUnitario = round(
            $ivaTotalLinea / $cantidadLinea,
            2
        );

        $reUnitario = round(
            $reTotalLinea / $cantidadLinea,
            2
        );

        $precioCosteUnitario = round(
            $totalCalculadoLinea / $cantidadLinea,
            2
        );

        /*
        * =========================
        * AJUSTE DE CÉNTIMOS
        * =========================
        *
        * Queremos garantizar:
        *
        * base + IVA + RE = total unitario
        */

        $descuadreUnitario = round(
            $precioCosteUnitario
            - (
                $baseUnitaria
                + $ivaUnitario
                + $reUnitario
            ),
            2
        );

        if ($descuadreUnitario !== 0.0) {

            // Preferimos ajustar IVA
            if ($ivaUnitario > 0) {
                $ivaUnitario = round(
                    $ivaUnitario + $descuadreUnitario,
                    2
                );
            } else {
                // Si no hay IVA ajustamos base
                $baseUnitaria = round(
                    $baseUnitaria + $descuadreUnitario,
                    2
                );
            }
        }

        /*
        * =========================
        * GUARDAR DATOS DE COSTE
        * =========================
        */

        $stockMovimiento->setCosteUnitarioBase(
            $baseUnitaria
        );

        $stockMovimiento->setPorcentajeIva(
            $ivaPct
        );

        $stockMovimiento->setImporteIvaUnitario(
            $ivaUnitario
        );

        $stockMovimiento->setTieneRecargoEquivalencia(
            $tieneRe
        );

        $stockMovimiento->setPorcentajeRecargoEquivalencia(
            $rePct
        );

        $stockMovimiento->setImporteRecargoUnitario(
            $reUnitario
        );

        $stockMovimiento->setPrecioCosteUnitario(
            $precioCosteUnitario
        );

        /*
        * =========================
        * OBSERVACIONES
        * =========================
        */

        $stockMovimiento->setObservaciones(
            'Entrada en stock desde factura proveedor '
            . ($factura->getNumeroFactura() ?: 'sin número')
            . ' · Proveedor: '
            . ($factura->getProveedorNombre() ?: '-')
            . ' · Cantidad: '
            . $cantidadAsignada
            . ' de '
            . $cantidadLinea
        );

        $this->em->persist($stockMovimiento);
    

    }    

    private function calcularEstadoAsignacionFactura(
        FacturaProveedor $factura
    ): string {
        $tienePendientes = false;
        $tieneParciales = false;
        $tieneAsignadas = false;

        foreach ($factura->getLineas() as $linea) {
            switch ($linea->getEstado()) {
                case 'pendiente':
                    $tienePendientes = true;
                    break;

                case 'parcial':
                    $tieneParciales = true;
                    break;

                case 'asignada':
                    $tieneAsignadas = true;
                    break;
            }
        }

        if ($tienePendientes || $tieneParciales) {
            return 'parcial';
        }

        if ($tieneAsignadas) {
            return 'asignada';
        }

        return 'pendiente';
    }

} 
