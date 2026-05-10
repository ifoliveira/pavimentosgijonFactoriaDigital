<?php

namespace App\Service\ProyectoGasto;

use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoGastoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;   
use App\Service\ForecastHandlerService;

class ProyectoGastoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ForecastHandlerService $forecastService
    ) {
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
        $totalReal = 0.0;

        foreach ($proyecto->getGastos() as $gasto) {
            if ($gasto->getEstado() !== 'cancelado') {
                $totalPrevisto += (float) $gasto->getImportePrevisto();
            }

            if ($gasto->getImporteReal() !== null && $gasto->getEstado() === 'pagado') {
                $totalReal += (float) $gasto->getImporteReal();
            }
        }

        if (method_exists($proyecto, 'setActualizadoEn')) {
            $proyecto->setActualizadoEn(new \DateTime());
        }

        // Aquí luego meterás los KPIs cuando los añadas a Proyecto:
        // $proyecto->setTotalGastoPrevisto(number_format($totalPrevisto, 2, '.', ''));
        // $proyecto->setTotalGastoReal(number_format($totalReal, 2, '.', ''));

        $this->em->flush();
    }
}