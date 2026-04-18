<?php

namespace App\Service\Proyecto;

use App\Entity\Proyecto;
use App\Repository\DocumentoRepository;
use Doctrine\ORM\EntityManagerInterface;

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

        $totalPresupuestado = '0.00';
        $totalFacturado = '0.00';
        $totalCobrado = '0.00';
        $fechaFinReal = null;

        if ($presupuestoInicial && in_array($presupuestoInicial->getEstadoComercial(), ['aceptado', 'convertido'], true)) {
            $totalPresupuestado = $this->normalizarDecimal($presupuestoInicial->getTotal());
        }

        if ($factura) {
            $totalFacturado = $this->normalizarDecimal($factura->getTotal());
            $totalCobrado = $this->normalizarDecimal($factura->getTotalCobrado());

            if ($factura->getEstadoCobro() === 'cobrado') {
                $fechaFinReal = new \DateTime();
            }
        }

        $proyecto->setTotalPresupuestado($totalPresupuestado);
        $proyecto->setTotalFacturado($totalFacturado);
        $proyecto->setTotalCobrado($totalCobrado);
        $proyecto->setFechaFinReal($fechaFinReal);



        if ($flush) {
            $this->em->flush();
        }
    }

    private function normalizarDecimal(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }


}