<?php
namespace App\Service;

use App\Repository\ForecastRepository;
use App\Entity\Forecast;

class ForecastService
{
    public function __construct(
        private ForecastRepository $forecastRepository,

    ) {}

    /**
     * Devuelve la lista de forecasts pendientes ordenados por fecha
     * y el array de datos acumulados para el gráfico.
     */
    public function getForecastPendiente(float $saldoInicial = 0.0): array
    {
        $forecastList = $this->forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        $chartData = [];
        $saldo = round($saldoInicial, 2);
        $chartData = [[
            'x' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'y' => $saldo,
        ]];

        foreach ($forecastList as $item) {
            $saldo += (float) $item->getImporteFr();

            $chartData[] = [
                'x' => $item->getFechaFr()->format('Y-m-d'),
                'y' => round($saldo, 2),
            ];
        }

        return [
            'list'      => $forecastList,
            'chartData' => $chartData,
        ];
    }

}
