<?php

namespace App\Service\ProyectoGasto;

use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoGastoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;   
use App\Service\ForecastHandlerService;
use App\Repository\FacturaProveedorLineaAsignacionRepository;
use App\Service\Efectivo\EfectivoService;
use App\Entity\FacturaProveedor;
use App\Entity\FacturaProveedorLinea;

class ProyectoGastoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ForecastHandlerService $forecastService,
        private FacturaProveedorLineaAsignacionRepository $facturaProveedorLineaAsignacionRepository,
        private EfectivoService $efectivoService

    ) {
    }

    public function confirmar(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
            throw new \LogicException('No se puede confirmar un gasto cancelado.');
        }

        if ($gasto->getEstado() === ProyectoGasto::ESTADO_PAGADO) {
            return;
        }

        $gasto->confirmar();

        if ($gasto->getImporteReal() === null) {
            $gasto->setImporteReal($gasto->getImportePrevisto());
        }

        $this->guardarCambios($gasto);
    }

    public function marcarPendientePagoBanco(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
            throw new \LogicException('No se puede pagar un gasto cancelado.');
        }

        if ($gasto->getImporteReal() === null) {
            $gasto->setImporteReal($gasto->getImportePrevisto());
        }

        $gasto->marcarPendientePagoBanco();

        $this->guardarCambios($gasto);
    }   

    public function marcarPagadoEnEfectivo(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() !== ProyectoGasto::ESTADO_CONFIRMADO) {
            throw new \LogicException(
                'Solo se pueden pagar gastos confirmados.'
            );
        }

        $importe = $gasto->getImporteReal()
            ?? $gasto->getImportePrevisto();

        $movimiento = $this->efectivoService->registrarSalida(
            importe: $importe,
            concepto: $gasto->getConcepto(),
            fecha: new \DateTime()
        );

        $gasto->setEfectivoMovimiento($movimiento);
        $gasto->setBancoMovimiento(null);

        $gasto->setEstado(ProyectoGasto::ESTADO_PAGADO);
        $gasto->setFechaPagado(new \DateTime());
        $gasto->setFechaReal(new \DateTime());


        /*
        * Al haberse producido el pago, el Forecast
        * correspondiente debe quedar realizado/resuelto.
        *
        * Aquí usarías tu lógica actual.*/
        $this->forecastService->resolverForecast($gasto->getForecast());

        $gasto->marcarActualizado();

        $this->em->flush();

        $this->recalcularProyecto($gasto->getProyecto());
    }

    public function marcarPagado(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
            throw new \LogicException('No se puede pagar un gasto cancelado.');
        }

        if ($gasto->getImporteReal() === null) {
            $gasto->setImporteReal($gasto->getImportePrevisto());
        }

        $gasto->marcarPagado();

        $this->guardarCambios($gasto);
    }    

    public function cancelar(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_PAGADO) {
            throw new \LogicException('No se debería cancelar directamente un gasto pagado.');
        }

        $gasto->cancelar();

        $this->guardarCambios($gasto);
    }

    public function eliminar(ProyectoGasto $gasto): void
    {
        if (!$this->puedeEliminar($gasto)) {
            throw new \LogicException('Este gasto ya tiene trazabilidad y no se puede eliminar. Cancélalo.');
        }

        $proyecto = $gasto->getProyecto();
        $forecast = $gasto->getForecast();

        /*
        * =========================
        * DESHACER ASIGNACIÓN FACTURA
        * =========================
        */

        $asignacion = $this->facturaProveedorLineaAsignacionRepository
            ->findOneBy([
                'proyectoGasto' => $gasto,
            ]);

        if ($asignacion !== null) {

            $linea = $asignacion->getLinea();

            $factura = $linea->getFacturaProveedor();

            $factura->setEStadoAsignacion('pendiente');

            /*
            * Eliminamos la asignación que originó este gasto.
            */
            $this->em->remove($asignacion);

            /*
            * La línea vuelve a quedar pendiente de asignación.
            */
            if ($linea !== null) {
                $linea->setEstado (
                    'pendiente'
                );
            }
        }


        /*
        * =========================
        * FORECAST
        * =========================
        */

        if ($forecast !== null && $gasto->esManual()) {
            $this->em->remove($forecast);
        }

        /*
        * =========================
        * GASTO
        * =========================
        */

        $this->em->remove($gasto);

        $this->em->flush();

        if ($proyecto) {
            $this->recalcularProyecto($proyecto);
        }
    }

    public function puedeEliminar(ProyectoGasto $gasto): bool
    {
        return $gasto->getEstado() === ProyectoGasto::ESTADO_PREVISTO
            && $gasto->getBancoMovimiento() === null
            && $gasto->getEfectivoMovimiento() === null;
    }

    private function guardarCambios(ProyectoGasto $gasto): void
    {
        $gasto->marcarActualizado();

        $this->sincronizarForecastSiProcede($gasto);

        if ($gasto->getProyecto()) {
            $this->recalcularProyecto($gasto->getProyecto());
        }

        $this->em->flush();
    }

    public function sincronizarForecastSiProcede(ProyectoGasto $gasto): void
    {
        if (!$gasto->isGeneraForecast()) {
            return;
        }

        $this->forecastService->sincronizarForecastSiProcede($gasto);
    }

    public function recalcularProyecto(Proyecto $proyecto): void
    {
        $totalPrevisto = 0.0;
        $totalConfirmado = 0.0;
        $totalPagado = 0.0;

        foreach ($proyecto->getGastos() as $gasto) {
            if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
                continue;
            }

            $totalPrevisto += (float) $gasto->getImportePrevisto();

            if (in_array($gasto->getEstado(), [
                ProyectoGasto::ESTADO_CONFIRMADO,
                ProyectoGasto::ESTADO_PAGADO,
            ], true)) {
                $totalConfirmado += (float) $gasto->getImporteEfectivo();
            }

            if ($gasto->getEstado() === ProyectoGasto::ESTADO_PAGADO) {
                $totalPagado += (float) $gasto->getImporteEfectivo();
            }
        }

        if (method_exists($proyecto, 'setActualizadoEn')) {
            $proyecto->setActualizadoEn(new \DateTime());
        }

        // Cuando añadas estos campos:
        // $proyecto->setTotalGastoPrevisto(number_format($totalPrevisto, 2, '.', ''));
        // $proyecto->setTotalGastoConfirmado(number_format($totalConfirmado, 2, '.', ''));
        // $proyecto->setTotalGastoPagado(number_format($totalPagado, 2, '.', ''));

        // OJO: aquí NO haría flush si este método lo llama guardarCambios()
        // para evitar flush duplicados.
    }

    public function reactivar(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() !== ProyectoGasto::ESTADO_CANCELADO) {
            throw new \LogicException('Solo se pueden reactivar gastos cancelados.');
        }

        $gasto->setEstado(ProyectoGasto::ESTADO_PREVISTO);
        $gasto->marcarActualizado();

        $this->sincronizarForecastSiProcede($gasto);

        if ($gasto->getProyecto()) {
            $this->recalcularProyecto($gasto->getProyecto());
        }

        $this->em->flush();
    }    

    public function guardar(ProyectoGasto $gasto, bool $esNuevo = false): void
    {
        /*
        * 1. Calcular desglose previsto
        */
        $total = (float) $gasto->getImportePrevisto();
        $tipoIva = (float) ($gasto->getTipoIvaPrevisto() ?? 0);

        if ($tipoIva > 0) {
            $base = $total / (1 + ($tipoIva / 100));
            $iva = $total - $base;
        } else {
            $base = $total;
            $iva = 0;
        }

        $gasto->setBasePrevista(
            number_format($base, 2, '.', '')
        );

        $gasto->setIvaPrevisto(
            number_format($iva, 2, '.', '')
        );

        /*
        * De momento, si no estamos introduciendo RE manualmente,
        * lo dejamos a cero.
        */
        if ($gasto->getRecargoPrevisto() === null) {
            $gasto->setRecargoPrevisto('0.00');
        }

        /*
        * 2. Auditoría
        */
        $gasto->marcarActualizado();

        /*
        * 3. Persist únicamente si es nuevo.
        *
        * En edición Doctrine ya está gestionando la entidad.
        */
        if ($esNuevo) {
            $this->em->persist($gasto);
        }

        /*
        * 4. Crear / modificar / eliminar Forecast según corresponda.
        */
        $this->sincronizarForecastSiProcede($gasto);

        /*
        * 5. Guardamos gasto + forecast juntos.
        */
        $this->em->flush();

        /*
        * 6. Recalcular situación económica del proyecto.
        */
        $this->recalcularProyecto($gasto->getProyecto());
    }    

    public function crearDesdeFacturaProveedor(
        Proyecto $proyecto,
        FacturaProveedor $factura,
        FacturaProveedorLinea $linea,
        float $importeAsignado,
        float $cantidadAsignada,
        float $cantidadLinea
    ): ProyectoGasto {

        $gasto = new ProyectoGasto();

        $gasto->setProyecto($proyecto);
        $gasto->setOrigen(ProyectoGasto::ORIGEN_FACTURA_PROVEEDOR);
        $gasto->setCategoria('materiales');

        $gasto->setConcepto(
            ($factura->getProveedorNombre() ?: 'Proveedor')
            . ' - '
            . ($linea->getDescripcion() ?: 'Línea factura')
        );

        $gasto->setProveedor(
            $factura->getProveedorNombre()
        );

        $forecast = $factura->getForecasts()->first() ?: null;

        $gasto->setFechaPrevista(
            $forecast?->getFechaFr() ?? new \DateTime()
        );

        /*
        * ------------------------------------------------------------
        * IMPORTES
        * ------------------------------------------------------------
        */

        $gasto->setImportePrevisto(
            number_format($importeAsignado, 2, '.', '')
        );

        /*
        * Al venir de una factura, conocemos ya el importe real.
        */
        $gasto->setImporteReal(
            number_format($importeAsignado, 2, '.', '')
        );

        /*
        * Calculamos qué parte de la línea corresponde a esta asignación.
        */
        $proporcion = $cantidadLinea > 0
            ? $cantidadAsignada / $cantidadLinea
            : 1;

        $baseReal =
            (float) ($linea->getImporteBruto() ?? 0)
            * $proporcion;

        $ivaReal =
            (float) ($linea->getImporteIva() ?? 0)
            * $proporcion;

        $recargoReal =
            (float) ($linea->getImporteRecargoEquivalencia() ?? 0)
            * $proporcion;

        $gasto->setBaseReal(
            number_format($baseReal, 2, '.', '')
        );

        $gasto->setTipoIvaReal(
            $linea->getPorcentajeIva() !== null
                ? number_format(
                    (float) $linea->getPorcentajeIva(),
                    2,
                    '.',
                    ''
                )
                : null
        );

        $gasto->setIvaReal(
            number_format($ivaReal, 2, '.', '')
        );

        $gasto->setRecargoReal(
            number_format($recargoReal, 2, '.', '')
        );

        /*
        * ------------------------------------------------------------
        * ESTADO
        * ------------------------------------------------------------
        */

        $gasto->setEstado(
            ProyectoGasto::ESTADO_CONFIRMADO
        );

        /*
        * No creamos Forecast.
        * Utilizamos el que ya pertenece a la factura.
        */
        $gasto->setForecast($forecast);
        $gasto->setGeneraForecast(false);

        $gasto->setNotas(
            'Generado desde factura proveedor '
            . ($factura->getNumeroFactura() ?: 'sin número')
            . ' · Línea: '
            . ($linea->getDescripcion() ?: '-')
            . ' · Cantidad asignada: '
            . $cantidadAsignada
            . ' de '
            . $cantidadLinea
        );

        $gasto->marcarActualizado();

        $this->em->persist($gasto);

        return $gasto;
    }

    public function confirmarDesdeFacturaProveedor(
        ProyectoGasto $gasto,
        FacturaProveedor $factura,
        FacturaProveedorLinea $linea,
        float $importeAsignado,
        float $cantidadAsignada,
        float $cantidadLinea
    ): ProyectoGasto {

        if ($gasto->getEstado() !== ProyectoGasto::ESTADO_PREVISTO) {
            throw new \LogicException(
                'Solo se puede asociar una factura a un gasto previsto.'
            );
        }

        if (!$gasto->esManual()) {
            throw new \LogicException(
                'Este gasto no es una previsión manual.'
            );
        }

        /*
        * ============================================================
        * IMPORTES REALES
        * ============================================================
        */

        $proporcion = $cantidadLinea > 0
            ? $cantidadAsignada / $cantidadLinea
            : 1;

        $baseReal =
            (float) ($linea->getBase() ?? 0)
            * $proporcion;

        $ivaReal =
            (float) ($linea->getIva() ?? 0)
            * $proporcion;

        $recargoReal =
            (float) ($linea->getImporteRecargoEquivalencia() ?? 0)
            * $proporcion;

        $gasto->setBaseReal(
            number_format($baseReal, 2, '.', '')
        );

        $gasto->setTipoIvaReal(
            $linea->getPorcentajeIva() !== null
                ? number_format(
                    (float) $linea->getPorcentajeIva(),
                    2,
                    '.',
                    ''
                )
                : null
        );

        $gasto->setIvaReal(
            number_format($ivaReal, 2, '.', '')
        );

        $gasto->setRecargoReal(
            number_format($recargoReal, 2, '.', '')
        );

        $gasto->setImporteReal(
            number_format($importeAsignado, 2, '.', '')
        );

        /*
        * ============================================================
        * DATOS REALES DEL PROVEEDOR
        * ============================================================
        */

        $gasto->setProveedor(
            $factura->getProveedorNombre()
        );

        $gasto->setOrigen($gasto::ORIGEN_FACTURA_PROVEEDOR);  

        /*
        * ============================================================
        * ESTADO
        * ============================================================
        */

        $gasto->setEstado(
            ProyectoGasto::ESTADO_CONFIRMADO
        );

        $gasto->marcarActualizado();

        /*
        * ============================================================
        * RESOLVER FORECAST MANUAL
        * ============================================================
        *
        * Este gasto nació manualmente y pudo crear su propio Forecast.
        *
        * Ahora que existe una FacturaProveedor real, la tesorería
        * futura pasa a estar representada por los Forecast/vencimientos
        * de la propia factura.
        *
        * Por tanto:
        *
        * - eliminamos el Forecast manual para no duplicar tesorería
        * - dejamos ProyectoGasto.forecast = null
        * - NO asociamos el gasto a uno de los Forecast de la factura
        */

        $forecastManual = $gasto->getForecast();

        if ($forecastManual !== null) {

            /*
            * Primero rompemos la relación para evitar problemas
            * de FK / UnitOfWork.
            */
            $gasto->setForecast(null);
            

            /*
            * El Forecast manual deja de tener sentido:
            * la factura ya tiene sus propios Forecast.
            */
            $this->em->remove($forecastManual);
            $gasto->setGeneraForecast(false);
        }
        return $gasto;
    }

}