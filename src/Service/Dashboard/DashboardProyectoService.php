<?php

namespace App\Service\Dashboard;

use App\Entity\Proyecto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;
use App\Repository\ProyectoGastoRepository;

class DashboardProyectoService
{
    public function __construct(
        private ProyectoRepository $proyectoRepository,
        private DocumentoRepository $documentoRepository,
        private ProyectoGastoRepository $proyectoGastoRepository,
    ) {
    }

    public function getDashboardData(): array
    {
        $ultimosProyectos = $this->proyectoRepository->findUltimosProyectosDashboard(10);

        $proyectosEnriquecidos = [];
        foreach ($ultimosProyectos as $proyecto) {
            $presupuesto = $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto);
            $factura = $this->documentoRepository->findFacturaDeProyecto($proyecto);
            $situacion = $this->calcularSituacionProyecto($proyecto, $presupuesto, $factura);

            $proyectosEnriquecidos[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'factura' => $factura,
                'situacion' => $situacion,
                'margen' => $this->getCosteProyecto($proyecto) ? (float) $proyecto->getTotalFacturado() - $this->getCosteProyecto($proyecto) : null,
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

            if ($situacion && $situacionCalculada !== $situacion) {
                continue;
            }

            $resultado[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'factura' => $factura,
                'situacion' => $situacionCalculada,
            ];
        }

        return $resultado;
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

        if ($factura && in_array($factura->getEstadoCobro(), ['pendiente', 'parcial'], true)) {
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
        $margenEstimado = 0;

        foreach ($proyectos as $proyecto) {
            $presupuestado = (float) $proyecto->getTotalPresupuestado();
            $facturado = (float) $proyecto->getTotalFacturado();
            $cobrado = (float) $proyecto->getTotalCobrado();

            $pendienteCobro = max($facturado - $cobrado, 0);
            $costePrevisto = $this->getCosteProyecto($proyecto);

            $totalPresupuestado += $presupuestado;
            $totalFacturado += $facturado;
            $totalCobrado += $cobrado;
            $totalPendienteCobro += $pendienteCobro;
            $totalCostePrevisto += $costePrevisto;
        }

        $margenEstimado = $totalFacturado - $totalCostePrevisto;

        return [
            'totalPresupuestado' => $totalPresupuestado,
            'totalFacturado' => $totalFacturado,
            'totalCobrado' => $totalCobrado,
            'totalPendienteCobro' => $totalPendienteCobro,
            'totalCostePrevisto' => $totalCostePrevisto,
            'margenEstimado' => $margenEstimado,
        ];
    }

    private function getCosteProyecto(Proyecto $proyecto): float
    {
        return $this->proyectoGastoRepository->sumarImportePorProyecto($proyecto);
    }    

}