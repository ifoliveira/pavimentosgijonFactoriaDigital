<?php
namespace App\Service;

use App\Repository\ForecastRepository;

class ForecastService
{
    public function __construct(
        private ForecastRepository $forecastRepository,
    ) {}

    /**
     * Devuelve la lista de forecasts pendientes ordenados por fecha
     * y el array de datos acumulados para el gráfico.
     */
    public function getForecastPendiente(): array
    {
        $forecastList = $this->forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        $chartData = [];
        $acumulado = 0;

        foreach ($forecastList as $item) {
            $acumulado += $item->getImporteFr() * -1;

            $chartData[] = [
                'x' => $item->getFechaFr()->format('Y-m-d'),
                'y' => round($acumulado, 2),
            ];
        }

        return [
            'list'      => $forecastList,
            'chartData' => $chartData,
        ];
    }
}