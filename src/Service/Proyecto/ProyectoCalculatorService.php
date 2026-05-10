<?php

namespace App\Service\Proyecto;

use App\Entity\Proyecto;
use App\Repository\DocumentoRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Documento;

class ProyectoCalculatorService
{

    public function __construct(
        private DocumentoRepository $documentoRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function recalcularProyecto(Proyecto $proyecto, bool $flush = true): void
    {
        $presupuestoInicial = $this->documentoRepository->findPresupuestoInicialDeProyecto($proyecto);
        $factura = $this->documentoRepository->findFacturaDeProyecto($proyecto);
        $ticket = $this->documentoRepository->findTicketDeProyecto($proyecto);

        $totalPresupuestado = '0.00';

        if (
            $presupuestoInicial
            && in_array($presupuestoInicial->getEstadoComercial(), ['aceptado', 'convertido', 'entregado', 'borrador'], true)
        ) {
            $totalPresupuestado = $this->normalizarDecimal($presupuestoInicial->getTotal());
        }

        $totalFacturadoFloat = $this->calcularTotalFacturadoProyecto(
            factura: $factura,
            ticket: $ticket,
            presupuestoInicial: $presupuestoInicial
        );

        $totalCobradoFloat = $this->calcularTotalCobradoProyecto($proyecto);

        $proyecto->setTotalPresupuestado($totalPresupuestado);
        $proyecto->setTotalFacturado(number_format($totalFacturadoFloat, 2, '.', ''));
        $proyecto->setTotalCobrado(number_format($totalCobradoFloat, 2, '.', ''));

        if ($totalFacturadoFloat > 0 && $totalCobradoFloat >= $totalFacturadoFloat) {
            $proyecto->setFechaFinReal(new \DateTime());
        } else {
            $proyecto->setFechaFinReal(null);
        }

        if ($flush) {
            $this->em->flush();
        }
    }

    private function calcularTotalFacturadoProyecto(
        ?Documento $factura,
        ?Documento $ticket,
        ?Documento $presupuestoInicial
    ): float {
        $totalFiscal = (float) ($factura?->getTotal() ?? 0)
            + (float) ($ticket?->getTotal() ?? 0);

        if ($totalFiscal > 0) {
            return $totalFiscal;
        }

        if (
            $presupuestoInicial
            && in_array($presupuestoInicial->getEstadoComercial(), ['aceptado', 'convertido', 'entregado'], true)
        ) {
            return (float) $presupuestoInicial->getTotal();
        }

        return 0.0;
    }

    private function calcularTotalCobradoProyecto(Proyecto $proyecto): float
    {
        $total = 0.0;

        foreach ($proyecto->getCobros() as $cobro) {
            $total += (float) $cobro->getImporteBruto();
        }

        return $total;
    }
        
    private function normalizarDecimal(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    public function recalcularProyectoDesdeDocumento(int $documentoId, bool $flush = true): void
    {
        $documento = $this->documentoRepository->find($documentoId);

        if (!$documento) {
            throw new \RuntimeException('Documento no encontrado.');
        }

        $proyecto = $documento->getProyecto();

        if (!$proyecto) {
            return;
        }

        $this->recalcularProyecto($proyecto, $flush);
    }    


}