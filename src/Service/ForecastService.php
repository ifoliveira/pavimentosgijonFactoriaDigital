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
    public function getForecastPendiente(
        float $saldoInicial = 0.0,
        ?\DateTimeInterface $desde = null,
        ?\DateTimeInterface $hasta = null,
        ?\DateTimeInterface $fechaInicioGrafico = null,
    ): array {
        $todosLosForecasts = $this->forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        $saldo = round($saldoInicial, 2);
        if ($desde !== null) {
            foreach ($todosLosForecasts as $forecast) {
                if ($forecast->getFechaFr() < $desde) {
                    $saldo += (float) $forecast->getImporteFr();
                }
            }
        }

        $forecastList = $todosLosForecasts;
        if ($desde !== null || $hasta !== null) {
            $forecastList = array_values(array_filter(
                $todosLosForecasts,
                static function (Forecast $forecast) use ($desde, $hasta): bool {
                    $fecha = $forecast->getFechaFr();

                    return ($desde === null || $fecha >= $desde)
                        && ($hasta === null || $fecha <= $hasta);
                }
            ));
        }

        $chartData = [];
        $chartData = [[
            'x' => ($fechaInicioGrafico ?? new \DateTimeImmutable('today'))->format('Y-m-d'),
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
