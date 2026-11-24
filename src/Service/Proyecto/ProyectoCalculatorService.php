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
        $facturaCompleta = $this->documentoRepository->findFacturaCompletaDeProyecto($proyecto);
        $ticket = $this->documentoRepository->findTicketDeProyecto($proyecto);
        $presupuestosAceptados = $this->documentoRepository->findPresupuestosAceptadosDeProyecto($proyecto);

        $totalPresupuestado = '0.00';
        foreach ($presupuestosAceptados as $presupuesto) {
            $totalPresupuestado = bcadd($totalPresupuestado, $this->normalizarDecimal($presupuesto->getTotal()), 2);
        }

        $totalFacturadoFloat = $this->calcularTotalFacturadoProyecto(
            facturaCompleta: $facturaCompleta,
            ticket: $ticket,
            proyecto: $proyecto
        );

        $totalCobradoFloat = $this->calcularTotalCobradoProyecto($proyecto);

        $proyecto->setTotalPresupuestado($totalPresupuestado);
        $proyecto->setTotalFacturado(number_format($totalFacturadoFloat, 2, '.', ''));
        $proyecto->setTotalCobrado(number_format($totalCobradoFloat, 2, '.', ''));

        if ($facturaCompleta !== null) {
            $cobradoFinal = '0.00';
            foreach ($proyecto->getCobros() as $cobro) {
                if ($cobro->getFacturaAnticipo() === null) {
                    $cobradoFinal = bcadd($cobradoFinal, $cobro->getImporteBruto(), 2);
                }
            }
            foreach ($facturaCompleta->getCobros() as $cobro) {
                $cobradoFinal = bcadd($cobradoFinal, $cobro->getImporteBruto(), 2);
            }
            $facturaCompleta->setTotalCobrado($cobradoFinal);
            $facturaCompleta->setEstadoCobro(
                bccomp($cobradoFinal, $facturaCompleta->getTotal(), 2) >= 0
                    ? 'cobrado' : (bccomp($cobradoFinal, '0', 2) > 0 ? 'parcial' : 'pendiente')
            );
        }

        // La finalización de la obra es manual e independiente del cobro.
        // Un recálculo económico nunca debe cerrar ni reabrir el proyecto.

        if ($flush) {
            $this->em->flush();
        }
    }

    private function calcularTotalFacturadoProyecto(
        ?Documento $facturaCompleta,
        ?Documento $ticket,
        Proyecto $proyecto
    ): float {
        $totalFiscal = (float) ($facturaCompleta?->getTotal() ?? 0)
            + (float) ($ticket?->getTotal() ?? 0);

        $vistos = new \SplObjectStorage();
        foreach ($proyecto->getCobros() as $cobro) {
            $anticipo = $cobro->getFacturaAnticipo();
            if ($anticipo !== null && !$vistos->contains($anticipo)) {
                $totalFiscal += (float) $anticipo->getTotal();
                $vistos->attach($anticipo);
            }
        }

        return $totalFiscal;
    }

    private function calcularTotalCobradoProyecto(Proyecto $proyecto): float
    {
        $total = 0.0;

        foreach ($proyecto->getCobros() as $cobro) {
            $total += (float) $cobro->getImporteBruto();
        }
        foreach ($proyecto->getDocumentos() as $documento) {
            if (in_array($documento->getTipoDocumento(), ['factura', 'ticket', 'ticket_rectificativo'], true)) {
                foreach ($documento->getCobros() as $cobro) {
                    $total += (float) $cobro->getImporteBruto();
                }
            }
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
