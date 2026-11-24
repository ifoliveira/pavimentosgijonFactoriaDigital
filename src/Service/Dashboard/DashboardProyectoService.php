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

        $contadores = $this->getContadoresSituacion();
        $sinPresupuesto = $contadores['sin_presupuesto'];
        $presupuestoBorrador = $contadores['presupuesto_borrador'];
        $aceptadoOConvertido = $this->proyectoRepository->countProyectosConPresupuestoAceptadoOConvertido();
        $facturados = $this->proyectoRepository->countProyectosFacturados();
        $pendienteCobro = $contadores['facturado_pendiente'];
        $cerrados = $contadores['cerrado'];
        $entregados = $contadores['presupuesto_entregado'];
        $economico = $this->getResumenEconomico();

        return [
            'contadoresSituacion' => $contadores,
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
                'aceptadosSinFactura' => $contadores['aceptado_sin_factura'],
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

    private function getContadoresSituacion(): array
    {
        $contadores = array_fill_keys([
            'atencion', 'sin_presupuesto', 'presupuesto_borrador',
            'presupuesto_entregado', 'presupuesto_rechazado',
            'aceptado_sin_factura', 'facturado_pendiente', 'en_proceso', 'cerrado',
        ], 0);

        // Mismo conjunto sin paginar y misma clasificación que el listado.
        foreach ($this->proyectoRepository->buscarProyectosDashboard(null, null, null, null) as $proyecto) {
            $situacion = $this->calcularSituacionProyecto(
                $proyecto,
                $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto),
                $this->documentoRepository->findFacturaDeProyecto($proyecto)
            );
            ++$contadores[$situacion];
            if ($this->coincideConFiltroDashboard($situacion, 'atencion')) {
                ++$contadores['atencion'];
            }
        }

        return $contadores;
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
                'en_proceso',
            ], true);
        }

        return $situacionCalculada === $filtro;
    }

    private function calcularSituacionProyecto(Proyecto $proyecto, $presupuesto, $factura): string
    {
        if ($proyecto->isRechazado()) {
            return 'presupuesto_rechazado';
        }

        // Un presupuesto antiguo rechazado no representa los demás vigentes.
        if ($presupuesto?->getEstadoComercial() === 'rechazado') {
            $presupuesto = null;
            foreach ($proyecto->getDocumentos() as $documento) {
                if ($documento->getTipoDocumento() === 'presupuesto'
                    && $documento->getEstadoComercial() !== 'rechazado') {
                    $presupuesto = $documento;
                    break;
                }
            }
        }

        $facturaFinal = $this->documentoRepository->findFacturaCompletaDeProyecto($proyecto);
        if ($proyecto->isObraFinalizada() && $facturaFinal !== null
            && bccomp($proyecto->getPendienteCobro(), '0', 2) === 0) {
            return 'cerrado';
        }

        // Ni el cobro de un anticipo ni la finalización física cierran la gestión.
        if ($factura && bccomp($proyecto->getPendienteCobro(), '0', 2) > 0) {
            return 'facturado_pendiente';
        }
        if ($facturaFinal !== null) {
            return 'en_proceso';
        }

        if (!$presupuesto) {
            return 'sin_presupuesto';
        }

        if ($presupuesto->getEstadoComercial() === 'borrador') {
            return 'presupuesto_borrador';
        }

        if ($presupuesto->getEstadoComercial() === 'entregado') {
            return 'presupuesto_entregado';
        }        

        if (in_array($presupuesto->getEstadoComercial(), ['aceptado', 'convertido'], true)) {
            return 'aceptado_sin_factura';
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

            $pendienteCobro = (float) $proyecto->getPendienteCobro();
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

            // Antes de la factura final, los anticipos no sustituyen al importe
            // contratado como referencia de los cobros pendientes.
            $importeBase = (float) $proyecto->getTotalOperacion();
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
        $tieneDocumentoFiscalCompleto = false;
        foreach ($proyecto->getDocumentos() as $documento) {
            if (!in_array($documento->getTipoDocumento(), ['factura', 'ticket', 'ticket_rectificativo'], true)) {
                continue;
            }

            // Una factura de anticipo no sustituye al presupuesto aceptado en
            // el margen de la obra. Sí se suma cuando ya existe el documento
            // final: ambos forman entonces la facturación fiscal completa.
            if (!$documento->esFacturaAnticipo()) {
                $tieneDocumentoFiscalCompleto = true;
            }
            $ingreso += (float) $documento->getBaseImponible();
        }

        if ($tieneDocumentoFiscalCompleto) {
            return $ingreso;
        }

        $ingresoPresupuestado = 0.0;
        foreach ($this->documentoRepository->findPresupuestosAceptadosDeProyecto($proyecto) as $presupuesto) {
            $ingresoPresupuestado += (float) $presupuesto->getBaseImponible();
        }

        return $ingresoPresupuestado;
    }

    public function getProyectosOperativos(): array
    {
        $proyectos = $this->proyectoRepository
            ->findTodosProyectosDashboard();

        $resultado = [];

        foreach ($proyectos as $proyecto) {

            $presupuesto = $this->documentoRepository
                ->findPresupuestoInicialDeProyecto($proyecto);

            // Los presupuestos rechazados se consultan en el histórico comercial.
            if ($proyecto->isRechazado()) {
                continue;
            }

            $factura = $this->documentoRepository
                ->findFacturaDeProyecto($proyecto);

            // La fase comercial no es todavía una obra operativa. Conservamos
            // proyectos históricos facturados aunque no tengan presupuesto.
            if (!$factura && !$this->tienePresupuestoAceptado($proyecto)) {
                continue;
            }

            $situacion = $this->calcularSituacionProyecto(
                $proyecto,
                $presupuesto,
                $factura
            );

            $facturaFinal = $this->documentoRepository
                ->findFacturaCompletaDeProyecto($proyecto);

            $cliente = $proyecto->getCliente();

            $totalCobrado = (float) $proyecto->getTotalCobrado();

            $total = (float) $proyecto->getTotalOperacion();

            $pendiente = (float) $proyecto->getPendienteCobro();
            // Solo desaparece del panel cuando no queda ninguna de estas tareas.
            if ($proyecto->isObraFinalizada() && $facturaFinal !== null && $pendiente <= 0) {
                continue;
            }
            $estadoCobro = $pendiente > 0
                ? ($totalCobrado > 0 ? 'parcial' : 'pendiente')
                : ($total > 0 || $totalCobrado > 0 ? 'cobrado' : 'sin_importe');

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
                'obraFinalizada' => $proyecto->isObraFinalizada(),
                'puedeFinalizarObra' => $presupuesto !== null
                    && in_array($presupuesto->getEstadoComercial(), ['aceptado', 'convertido'], true),
                'facturaFinal' => $facturaFinal,
                'puedeEmitirFacturaFinal' => $facturaFinal === null
                    && count($this->documentoRepository->findPresupuestosAceptadosDeProyecto($proyecto)) > 0,
                'estadoCobro' => $estadoCobro,

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

    private function tienePresupuestoAceptado(Proyecto $proyecto): bool
    {
        foreach ($proyecto->getDocumentos() as $documento) {
            if ($documento->getTipoDocumento() === 'presupuesto'
                && in_array($documento->getEstadoComercial(), ['aceptado', 'convertido'], true)) {
                return true;
            }
        }

        return false;
    }

}
