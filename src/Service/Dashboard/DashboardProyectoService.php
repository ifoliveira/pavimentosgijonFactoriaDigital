<?php

namespace App\Service\Dashboard;

use App\Entity\Proyecto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;

class DashboardProyectoService
{
    public function __construct(
        private ProyectoRepository $proyectoRepository,
        private DocumentoRepository $documentoRepository,
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
            ];
        }

        $sinPresupuesto = $this->proyectoRepository->countProyectosSinPresupuesto();
        $presupuestoBorrador = $this->proyectoRepository->countProyectosConPresupuestoBorrador();
        $aceptadoOConvertido = $this->proyectoRepository->countProyectosConPresupuestoAceptadoOConvertido();
        $facturados = $this->proyectoRepository->countProyectosFacturados();
        $pendienteCobro = $this->proyectoRepository->countProyectosPendientesCobro();
        $cerrados = $this->proyectoRepository->countProyectosCerrados();

        return [
            'resumen' => [
                'sinPresupuesto' => $sinPresupuesto,
                'presupuestoBorrador' => $presupuestoBorrador,
                'aceptadoOConvertido' => $aceptadoOConvertido,
                'facturados' => $facturados,
                'pendienteCobro' => $pendienteCobro,
                'cerrados' => $cerrados,
            ],
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

        if ($presupuesto->getEstadoComercial() === 'borrador') {
            return 'presupuesto_borrador';
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
}