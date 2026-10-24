<?php

namespace App\Service;

use App\Entity\Forecast;
use App\Service\Dashboard\DashboardProyectoService;

/** Proyección anual de caja: forecast real + compromisos de obras estimados. */
final class FlujoCajaAnualService
{
    private const IVA_ESTIMADO = 0.21;

    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly DashboardProyectoService $dashboardProyectoService,
    ) {
    }

    /** @return array<string, mixed> */
    public function calcular(float $saldoActual): array
    {
        $hoy = new \DateTimeImmutable('today');
        $finAnio = $hoy->setDate((int) $hoy->format('Y'), 12, 31);
        $mesActual = (int) $hoy->format('n');
        $meses = $this->crearMeses((int) $hoy->format('Y'), $saldoActual);
        $forecast = $this->forecastService->getForecastPendiente($saldoActual)['list'];

        $cobros = 0.0;
        $pagos = 0.0;
        foreach ($forecast as $movimiento) {
            $fecha = \DateTimeImmutable::createFromInterface($movimiento->getFechaFr());
            if ($fecha > $finAnio) {
                continue;
            }

            $importe = (float) $movimiento->getImporteFr();
            $mes = max($mesActual, (int) $fecha->format('n'));
            if ($importe >= 0) {
                $cobros += $importe;
                $meses[$mes]['cobrosForecast'] += $importe;
            } else {
                $pagos += abs($importe);
                $meses[$mes]['pagosForecast'] += abs($importe);
            }
        }

        $cobrosProyectoEstimados = 0.0;
        foreach ($this->dashboardProyectoService->getCobrosPendientesProyectos((int) $hoy->format('Y')) as $cobro) {
            $mes = max($mesActual, (int) $cobro['fecha']->format('n'));
            $cobrosProyectoEstimados += $cobro['importe'];
            $meses[$mes]['cobrosProyectoEstimados'] += $cobro['importe'];
        }

        $compromisosBase = 0.0;
        foreach ($this->dashboardProyectoService->getCompromisosPendientes() as $compromiso) {
            $gasto = $compromiso['gasto'];
            $fecha = $gasto->getFechaPrevista()
                ? \DateTimeImmutable::createFromInterface($gasto->getFechaPrevista())
                : $hoy;
            if ($fecha > $finAnio) {
                continue;
            }

            $importeConIva = round($compromiso['importe'] * (1 + self::IVA_ESTIMADO), 2);
            $compromisosBase += $compromiso['importe'];
            $mes = max($mesActual, (int) $fecha->format('n'));
            $meses[$mes]['compromisosProyecto'] += $importeConIva;
        }

        $saldo = $saldoActual;
        foreach ($meses as &$mes) {
            $saldo += $mes['cobrosForecast'] + $mes['cobrosProyectoEstimados'] - $mes['pagosForecast'] - $mes['compromisosProyecto'];
            $mes['saldoProyectado'] = round($saldo, 2);
        }
        unset($mes);

        $compromisosIva = round($compromisosBase * self::IVA_ESTIMADO, 2);
        $compromisosConIva = round($compromisosBase + $compromisosIva, 2);

        return [
            'anio' => (int) $hoy->format('Y'),
            'fechaCorte' => $hoy,
            'fechaFin' => $finAnio,
            'saldoActual' => round($saldoActual, 2),
            'cobrosForecast' => round($cobros, 2),
            'cobrosProyectoEstimados' => round($cobrosProyectoEstimados, 2),
            'pagosForecast' => round($pagos, 2),
            'compromisosBase' => round($compromisosBase, 2),
            'compromisosIva' => $compromisosIva,
            'compromisosConIva' => $compromisosConIva,
            'salidasPendientesEstimadas' => round($pagos + $compromisosConIva, 2),
            'saldoForecastReal' => round($saldoActual + $cobros - $pagos, 2),
            'saldoProyectadoFinAnio' => round($saldoActual + $cobros + $cobrosProyectoEstimados - $pagos - $compromisosConIva, 2),
            'meses' => $meses,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function crearMeses(int $anio, float $saldoActual): array
    {
        $nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $meses = [];
        foreach ($nombres as $indice => $nombre) {
            $meses[$indice + 1] = [
                'nombre' => $nombre,
                'cobrosForecast' => 0.0,
                'cobrosProyectoEstimados' => 0.0,
                'pagosForecast' => 0.0,
                'compromisosProyecto' => 0.0,
                'saldoProyectado' => $saldoActual,
                'pasado' => $indice + 1 < (int) (new \DateTimeImmutable('today'))->format('n'),
            ];
        }
        return $meses;
    }
}
