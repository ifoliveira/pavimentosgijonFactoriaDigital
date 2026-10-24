<?php

namespace App\Service\Dashboard;

use App\Entity\Proyecto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;
use App\Repository\ProyectoGastoRepository;
use App\Entity\ProyectoGasto;

class DashboardProyectoService
{
    public function __construct(
        private ProyectoRepository $proyectoRepository,
        private DocumentoRepository $documentoRepository,
        private ?ProyectoGastoRepository $proyectoGastoRepository = null,
    ) {
    }

    public function getDashboardData(): array
    {
        $ultimosProyectos = $this->proyectoRepository->findUltimosProyectosDashboard(100);

        $proyectosEnriquecidos = [];
        foreach ($ultimosProyectos as $proyecto) {

            $presupuesto = $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto);

            $factura = $this->documentoRepository->findFacturaDeProyecto($proyecto);

            // SOLO proyectos reales
            if (!$factura) {
                continue;
            }

            if ($this->calcularSituacionProyecto($proyecto, $presupuesto, $factura) === 'cerrado') {
                continue;
            }            

            $situacion = $this->calcularSituacionProyecto(
                $proyecto,
                $presupuesto,
                $factura
            );

            $resumenEconomico = $this->getResumenProyecto($proyecto);
            $proyectosEnriquecidos[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'factura' => $factura,
                'situacion' => $situacion,
                'economico' => $resumenEconomico,
                // Se conserva para los consumidores existentes del servicio.
                'margen' => $resumenEconomico['margenEstimado'],
            ];
        }

        $sinPresupuesto = $this->proyectoRepository->countProyectosSinPresupuesto();
        $presupuestoBorrador = $this->proyectoRepository->countProyectosConPresupuestoBorrador();
        $aceptadoOConvertido = $this->proyectoRepository->countProyectosConPresupuestoAceptadoOConvertido();
        $facturados = $this->proyectoRepository->countProyectosFacturados();
        $pendienteCobro = $this->proyectoRepository->countProyectosPendientesCobro();
        $cerrados = $this->proyectoRepository->countProyectosCerrados();
        $entregados = $this->proyectoRepository->countProyectosConPresupuestoEntregado();
        $economico = $this->getResumenEconomico();

        return [
            'resumen' => [
                'sinPresupuesto' => $sinPresupuesto,
                'presupuestoBorrador' => $presupuestoBorrador,
                'aceptadoOConvertido' => $aceptadoOConvertido,
                'facturados' => $facturados,
                'pendienteCobro' => $pendienteCobro,
                'cerrados' => $cerrados,
                'entregados' => $entregados,
                ],
            'economico' => $economico,
            'pendientes' => [
                'sinPresupuesto' => $sinPresupuesto,
                'presupuestoBorrador' => $presupuestoBorrador,
                'aceptadosSinFactura' => $this->proyectoRepository->countProyectosAceptadosSinFactura(),
                'pendienteCobro' => $pendienteCobro,
            ],
            'ultimosProyectos' => $proyectosEnriquecidos,
        ];
    }

    public function buscarProyectos(
        ?string $nombre,
        ?string $cliente,
        ?string $telefono,
        ?string $situacion
    ): array {
        $proyectos = $this->proyectoRepository->buscarProyectosDashboard($nombre, $cliente, $telefono, $situacion);

        $resultado = [];
        foreach ($proyectos as $proyecto) {
            $presupuesto = $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto);
            $factura = $this->documentoRepository->findFacturaDeProyecto($proyecto);
            $situacionCalculada = $this->calcularSituacionProyecto($proyecto, $presupuesto, $factura);

            if (!$this->coincideConFiltroDashboard($situacionCalculada, $situacion)) {
                continue;
            }

            $resultado[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'factura' => $factura,
                'situacion' => $situacionCalculada,
                'economico' => $this->getResumenProyecto($proyecto),
            ];
        }

        return $resultado;
    }

    public function buscarProyectosPaginados(
        ?string $nombre,
        ?string $cliente,
        ?string $telefono,
        ?string $situacion,
        int $pagina,
        int $porPagina = 20
    ): array {
        $resultado = $this->buscarProyectos($nombre, $cliente, $telefono, $situacion);
        $total = count($resultado);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);

        return [
            'resultados' => array_slice($resultado, ($pagina - 1) * $porPagina, $porPagina),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
        ];
    }

    private function coincideConFiltroDashboard(string $situacionCalculada, ?string $filtro): bool
    {
        if (!$filtro || $filtro === 'todos') {
            return true;
        }

        if ($filtro === 'atencion') {
            return in_array($situacionCalculada, [
                'sin_presupuesto',
                'presupuesto_borrador',
                'presupuesto_entregado',
                'aceptado_sin_factura',
                'facturado_pendiente',
            ], true);
        }

        return $situacionCalculada === $filtro;
    }

    private function calcularSituacionProyecto(Proyecto $proyecto, $presupuesto, $factura): string
    {
        if ($proyecto->getFechaFinReal() !== null) {
            return 'cerrado';
        }

        if (!$presupuesto) {
            return 'sin_presupuesto';
        }

        if ($presupuesto->getEstadoComercial() === 'rechazado') {
            return 'presupuesto_rechazado';
        }        

        if ($presupuesto->getEstadoComercial() === 'borrador') {
            return 'presupuesto_borrador';
        }

        if ($presupuesto->getEstadoComercial() === 'entregado') {
            return 'presupuesto_entregado';
        }        

        if (in_array($presupuesto->getEstadoComercial(), ['aceptado', 'convertido'], true) && !$factura) {
            return 'aceptado_sin_factura';
        }

        // La realidad económica prevalece sobre el texto de estado: una
        // factura con saldo pendiente debe permanecer en la bandeja de cobro,
        // incluso si su estadoCobro antiguo no se actualizó correctamente.
        if ($factura && (float) $factura->getTotalCobrado() < (float) $factura->getTotal()) {
            return 'facturado_pendiente';
        }

        if ($factura && $factura->getEstadoCobro() === 'cobrado') {
            return 'cerrado';
        }

        return 'en_proceso';
    }

    private function getResumenEconomico(): array
    {
        $proyectos = $this->proyectoRepository->findAll();

        $totalPresupuestado = 0;
        $totalFacturado = 0;
        $totalCobrado = 0;
        $totalPendienteCobro = 0;
        $totalCostePrevisto = 0;
        $totalIngresoSinIva = 0;
        $totalCosteConfirmado = 0;
        $totalPendienteConfirmar = 0;

        foreach ($proyectos as $proyecto) {
            $presupuestado = (float) $proyecto->getTotalPresupuestado();
            $facturado = (float) $proyecto->getTotalFacturado();
            $cobrado = (float) $proyecto->getTotalCobrado();

            $pendienteCobro = max($facturado - $cobrado, 0);
            $resumenProyecto = $this->getResumenProyecto($proyecto);
            $costePrevisto = $resumenProyecto['costeTotalEstimado'];

            $totalPresupuestado += $presupuestado;
            $totalFacturado += $facturado;
            $totalCobrado += $cobrado;
            $totalPendienteCobro += $pendienteCobro;
            $totalCostePrevisto += $costePrevisto;
            $totalIngresoSinIva += $this->getIngresoSinIva($proyecto);
            $totalCosteConfirmado += $resumenProyecto['costeConfirmado'];
            $totalPendienteConfirmar += $resumenProyecto['costePrevistoPendiente'];
        }

        $margenEstimado = $totalIngresoSinIva - $totalCostePrevisto;

        return [
            'totalPresupuestado' => $totalPresupuestado,
            'totalFacturado' => $totalFacturado,
            'totalCobrado' => $totalCobrado,
            'totalPendienteCobro' => $totalPendienteCobro,
            'totalCostePrevisto' => $totalCostePrevisto,
            'margenEstimado' => $margenEstimado,
            'totalCosteConfirmado' => $totalCosteConfirmado,
            'totalPendienteConfirmar' => $totalPendienteConfirmar,
        ];
    }

    /** @return array<string, float> */
    public function getResumenProyecto(Proyecto $proyecto): array
    {
        $costeConfirmado = 0.0;
        $costePrevistoPendiente = 0.0;

        foreach ($proyecto->getGastos() as $gasto) {
            if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
                continue;
            }

            // Hay datos históricos cuya transición de estado no se registró,
            // pero el coste real sí es fiable y debe prevalecer.
            if ($gasto->getBaseReal() !== null || $gasto->getImporteReal() !== null) {
                $costeConfirmado += $this->costeSinIva($gasto, true);
                continue;
            }

            if ($gasto->getEstado() === ProyectoGasto::ESTADO_PREVISTO) {
                $costePrevistoPendiente += $this->costeSinIva($gasto, false);
            }
        }

        $ingreso = $this->getIngresoSinIva($proyecto);
        $costeTotalEstimado = $costeConfirmado + $costePrevistoPendiente;
        $margenEstimado = $ingreso - $costeTotalEstimado;

        return [
            'ingresoSinIva' => $ingreso,
            'costeConfirmado' => $costeConfirmado,
            'costePrevistoPendiente' => $costePrevistoPendiente,
            'costeTotalEstimado' => $costeTotalEstimado,
            'margenEstimado' => $margenEstimado,
            'margenSobreVentas' => $ingreso > 0 ? ($margenEstimado / $ingreso) * 100 : 0.0,
        ];
    }

    /**
     * Agrupa los costes de una colección de proyectos ya filtrada por el
     * consumidor (por ejemplo, por ejercicio de facturación).
     *
     * @param iterable<Proyecto> $proyectos
     * @return array{costeConfirmado: float, costePrevistoPendiente: float, costeTotalEstimado: float}
     */
    public function getResumenCostesProyectos(iterable $proyectos): array
    {
        $resumen = [
            'costeConfirmado' => 0.0,
            'costePrevistoPendiente' => 0.0,
            'costeTotalEstimado' => 0.0,
        ];

        foreach ($proyectos as $proyecto) {
            $costes = $this->getResumenProyecto($proyecto);
            $resumen['costeConfirmado'] += $costes['costeConfirmado'];
            $resumen['costePrevistoPendiente'] += $costes['costePrevistoPendiente'];
            $resumen['costeTotalEstimado'] += $costes['costeTotalEstimado'];
        }

        return $resumen;
    }

    /** @return array<int, array{gasto: ProyectoGasto, importe: float}> */
    public function getCompromisosPendientes(): array
    {
        if ($this->proyectoGastoRepository === null) {
            return [];
        }

        return array_map(fn (ProyectoGasto $gasto) => [
            'gasto' => $gasto,
            'importe' => $this->costeSinIva($gasto, false),
        ], array_filter(
            $this->proyectoGastoRepository->findPrevistosDeProyectosAbiertos(),
            static fn (ProyectoGasto $gasto) => $gasto->getBaseReal() === null && $gasto->getImporteReal() === null
        ));
    }

    /**
     * Cobros que aún no figuran como realizados de presupuestos aceptados.
     * Son una previsión de proyecto, no un Forecast persistido: así no se
     * duplican cuando exista un vencimiento real.
     *
     * @return array<int, array{proyecto: Proyecto, importe: float, fecha: \DateTimeImmutable}>
     */
    public function getCobrosPendientesProyectos(int $anio): array
    {
        $finAnio = new \DateTimeImmutable(sprintf('%d-12-31', $anio));
        $cobros = [];

        foreach ($this->proyectoRepository->findAll() as $proyecto) {
            if ($proyecto->getFechaFinReal() !== null) {
                continue;
            }

            $presupuesto = $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto);
            if (!$presupuesto || !in_array($presupuesto->getEstadoComercial(), ['aceptado', 'convertido'], true)) {
                continue;
            }

            // totalFacturado ya contiene el importe comprometido de un
            // presupuesto aceptado sin factura. No se usa el mayor con el
            // presupuesto: de hacerlo, una factura completamente cobrada pero
            // inferior al presupuesto inicial seguiría apareciendo como cobro
            // pendiente.
            $importeBase = (float) $proyecto->getTotalFacturado();
            $pendiente = max($importeBase - (float) $proyecto->getTotalCobrado(), 0.0);
            if ($pendiente <= 0) {
                continue;
            }

            $fecha = $proyecto->getFechaFinPrevista()
                ? \DateTimeImmutable::createFromInterface($proyecto->getFechaFinPrevista())
                : \DateTimeImmutable::createFromInterface($proyecto->getFechaInicio())->modify('+4 months');

            // Esta vista es anual: una fecha estimada posterior se lleva al cierre.
            if ($fecha > $finAnio) {
                $fecha = $finAnio;
            }

            $cobros[] = [
                'proyecto' => $proyecto,
                'importe' => round($pendiente, 2),
                'fecha' => $fecha,
            ];
        }

        return $cobros;
    }

    private function getCosteProyecto(Proyecto $proyecto): float
    {
        return $this->getResumenProyecto($proyecto)['costeTotalEstimado'];
    }    

    private function costeSinIva(ProyectoGasto $gasto, bool $real): float
    {
        if ($real && $gasto->getBaseReal() !== null) {
            return (float) $gasto->getBaseReal();
        }
        if (!$real && $gasto->getBasePrevista() !== null) {
            return (float) $gasto->getBasePrevista();
        }

        $importe = (float) ($real ? $gasto->getImporteReal() : $gasto->getImportePrevisto());
        $iva = (float) ($real ? $gasto->getIvaReal() : $gasto->getIvaPrevisto());
        return $importe - $iva;
    }

    private function getIngresoSinIva(Proyecto $proyecto): float
    {
        $ingreso = 0.0;
        $tieneDocumentoFiscal = false;
        foreach ($proyecto->getDocumentos() as $documento) {
            if (!in_array($documento->getTipoDocumento(), ['factura', 'ticket', 'ticket_rectificativo'], true)) {
                continue;
            }

            $tieneDocumentoFiscal = true;
            $ingreso += (float) $documento->getBaseImponible();
        }

        if ($tieneDocumentoFiscal) {
            return $ingreso;
        }

        $presupuesto = $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto);
        return $presupuesto && in_array($presupuesto->getEstadoComercial(), ['aceptado', 'convertido'], true)
            ? (float) $presupuesto->getBaseImponible()
            : 0.0;
    }

    public function getProyectosOperativos(): array
    {
        $proyectos = $this->proyectoRepository
            ->findUltimosProyectosDashboard(100);

        $resultado = [];

        foreach ($proyectos as $proyecto) {

            $presupuesto = $this->documentoRepository
                ->findPresupuestoInicialDeProyecto($proyecto);

            $factura = $this->documentoRepository
                ->findFacturaDeProyecto($proyecto);

            $situacion = $this->calcularSituacionProyecto(
                $proyecto,
                $presupuesto,
                $factura
            );

            /*
            * Solo queremos obras reales:
            * aceptadas, facturadas o en proceso.
            */
            if (!in_array($situacion, [
                'aceptado_sin_factura',
                'facturado_pendiente',
                'en_proceso',
            ], true)) {
                continue;
            }

            $cliente = $proyecto->getCliente();

            /*
            * IMPORTE DE LA OPERACIÓN
            *
            * Si existe factura usamos el total facturado.
            * Si todavía no existe, usamos el presupuestado.
            */
            $totalFacturado = (float) $proyecto->getTotalFacturado();
            $totalPresupuestado = (float) $proyecto->getTotalPresupuestado();
            $totalCobrado = (float) $proyecto->getTotalCobrado();

            $total = $totalFacturado > 0
                ? $totalFacturado
                : $totalPresupuestado;

            $pendiente = max(
                $total - $totalCobrado,
                0
            );

            /*
            * GASTOS
            */
            $gastosPendientesCantidad = 0;
            $gastosPendientesImporte = 0.0;

            foreach ($proyecto->getGastos() as $gasto) {

                $estaPagadoOCancelado = in_array(
                    $gasto->getEstado(),
                    ['pagado', 'cancelado'],
                    true
                );

                $confirmadoDesdeFacturaProveedor =
                    $gasto->getEstado() === 'confirmado'
                    && $gasto->getOrigen() === 'factura_proveedor';

                if ($estaPagadoOCancelado || $confirmadoDesdeFacturaProveedor) {
                    continue;
                }

                $gastosPendientesCantidad++;

                if ($gasto->getImporteReal() !== null) {
                    $gastosPendientesImporte +=
                        (float) $gasto->getImporteReal();
                } else {
                    $gastosPendientesImporte +=
                        (float) $gasto->getImportePrevisto();
                }
                
            }

            $resultado[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'factura' => $factura,
                'situacion' => $situacion,

                'clienteNombre' =>
                    $cliente?->getNombreCl() ?? 'Sin cliente',

                'clienteTelefono' =>
                    $cliente?->getTelefono1Cl(),

                'direccion' =>
                    $cliente?->getDireccionCl() ?? '',

                'total' => $total,
                'cobrado' => $totalCobrado,
                'pendiente' => $pendiente,

                'gastosPendientesCantidad' =>
                    $gastosPendientesCantidad,

                'gastosPendientesImporte' =>
                    $gastosPendientesImporte,
            ];
        }

        return $resultado;
    }    

}
