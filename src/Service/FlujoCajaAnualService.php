<?php

namespace App\Service;

use App\Service\Dashboard\DashboardProyectoService;
use App\Service\Tesoreria\BolsaGastosService;

/** Vencimientos documentados + reserva global aún no cubierta por facturas. */
final class FlujoCajaAnualService
{
    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly DashboardProyectoService $dashboardProyectoService,
        private readonly BolsaGastosService $bolsaGastosService,
    ) {
    }

    /** @return array<string, mixed> */
    public function calcular(
        float $saldoActual,
        ?\DateTimeImmutable $hoy = null,
        ?int $anio = null,
    ): array
    {
        $hoy = ($hoy ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $anio ??= (int) $hoy->format('Y');
        $inicioAnio = new \DateTimeImmutable(sprintf('%d-01-01', $anio));
        $finAnio = new \DateTimeImmutable(sprintf('%d-12-31', $anio));
        $esAnioDelCorte = $anio === (int) $hoy->format('Y');
        $fechaCorte = $esAnioDelCorte ? $hoy : $inicioAnio;
        $mesActual = $esAnioDelCorte ? (int) $hoy->format('n') : 1;
        $meses = $this->crearMeses($fechaCorte, $saldoActual);
        $saldoInicialAnio = $saldoActual;
        $forecast = $this->forecastService->getForecastPendiente(
            $saldoActual,
            null,
            $finAnio,
            $hoy,
        )['list'];

        $gastosPorDia = [];
        $cobros = 0.0;
        $pagos = 0.0;
        foreach ($forecast as $movimiento) {
            $fecha = \DateTimeImmutable::createFromInterface($movimiento->getFechaFr());
            if ($fecha > $finAnio) {
                continue;
            }

            $importe = (float) $movimiento->getImporteFr();
            if (!$esAnioDelCorte && $fecha < $inicioAnio) {
                $saldoInicialAnio += $importe;
                continue;
            }

            $fecha = max($hoy, $fecha);
            $mes = max($mesActual, (int) $fecha->format('n'));
            if ($importe >= 0) {
                $cobros += $importe;
                $meses[$mes]['cobrosForecast'] += $importe;
            } else {
                $dia = $fecha->format('Y-m-d');
                $gastosPorDia[$dia] = ($gastosPorDia[$dia] ?? 0) + (int) round(abs($importe) * 100);
                $pagos += abs($importe);
                $meses[$mes]['pagosForecast'] += abs($importe);
            }
        }

        $cobrosProyectoEstimados = 0.0;
        foreach ($this->dashboardProyectoService->getCobrosPendientesProyectos($anio) as $cobro) {
            if (!$esAnioDelCorte && $cobro['fecha'] < $inicioAnio) {
                $saldoInicialAnio += $cobro['importe'];
                continue;
            }
            $mes = max($mesActual, (int) $cobro['fecha']->format('n'));
            $cobrosProyectoEstimados += $cobro['importe'];
            $meses[$mes]['cobrosProyectoEstimados'] += $cobro['importe'];
        }

        $bolsa = $this->bolsaGastosService->getResumen($hoy);
        $bolsaBase = 0.0;
        $bolsaIva = 0.0;
        $bolsaPosteriorAnio = 0.0;
        $bolsaPendientesAnio = [];
        foreach ($bolsa['pendientes'] as $pendiente) {
            $fecha = $pendiente['fechaPrevista'];
            if ($fecha > $finAnio) {
                $bolsaPosteriorAnio += $pendiente['totalCentimos'] / 100;
                continue;
            }
            if (!$esAnioDelCorte && $fecha < $inicioAnio) {
                $saldoInicialAnio -= $pendiente['totalCentimos'] / 100;
                continue;
            }
            $dia = max($fechaCorte, $fecha)->format('Y-m-d');
            $gastosPorDia[$dia] = ($gastosPorDia[$dia] ?? 0) + $pendiente['totalCentimos'];
            $bolsaBase += $pendiente['restanteCentimos'] / 100;
            $bolsaIva += $pendiente['ivaCentimos'] / 100;
            $bolsaPendientesAnio[] = $pendiente;
            $mes = max($mesActual, (int) $fecha->format('n'));
            $meses[$mes]['bolsaGastos'] += $pendiente['totalCentimos'] / 100;
        }

        $pagosSinVencimiento = 0.0;
        foreach ($bolsa['facturasSinVencimiento'] as $pendiente) {
            if ($pendiente['fecha'] > $finAnio) {
                continue;
            }
            if (!$esAnioDelCorte && $pendiente['fecha'] < $inicioAnio) {
                $saldoInicialAnio -= $pendiente['importe'];
                continue;
            }
            $mes = max($mesActual, (int) $pendiente['fecha']->format('n'));
            $dia = max($fechaCorte, $pendiente['fecha'])->format('Y-m-d');
            $gastosPorDia[$dia] = ($gastosPorDia[$dia] ?? 0) + (int) round($pendiente['importe'] * 100);
            $pagosSinVencimiento += $pendiente['importe'];
            $meses[$mes]['pagosSinVencimiento'] += $pendiente['importe'];
        }

        $saldo = $saldoInicialAnio;
        foreach ($meses as &$mes) {
            $saldo += $mes['cobrosForecast'] + $mes['cobrosProyectoEstimados'] - $mes['pagosForecast'] - $mes['pagosSinVencimiento'] - $mes['bolsaGastos'];
            $mes['saldoProyectado'] = round($saldo, 2);
        }
        unset($mes);

        $bolsaConIva = round($bolsaBase + $bolsaIva, 2);

        $gastosAcumulados = [];
        $acumuladoCentimos = 0;
        for ($dia = $fechaCorte; $dia <= $finAnio; $dia = $dia->modify('+1 day')) {
            $fecha = $dia->format('Y-m-d');
            $acumuladoCentimos += $gastosPorDia[$fecha] ?? 0;
            $gastosAcumulados[] = ['x' => $fecha, 'y' => $acumuladoCentimos / 100];
        }

        return [
            'gastosAcumulados' => $gastosAcumulados,
            'anio' => $anio,
            'fechaCorte' => $fechaCorte,
            'fechaFin' => $finAnio,
            'saldoActual' => round($saldoActual, 2),
            'saldoInicialAnio' => round($saldoInicialAnio, 2),
            'variacionAntesAnio' => round($saldoInicialAnio - $saldoActual, 2),
            'cobrosForecast' => round($cobros, 2),
            'cobrosProyectoEstimados' => round($cobrosProyectoEstimados, 2),
            'pagosForecast' => round($pagos, 2),
            'pagosSinVencimiento' => round($pagosSinVencimiento, 2),
            'bolsa' => $bolsa,
            'bolsaBase' => round($bolsaBase, 2),
            'bolsaIva' => round($bolsaIva, 2),
            'bolsaConIva' => $bolsaConIva,
            'bolsaPendientesAnio' => $bolsaPendientesAnio,
            'bolsaPosteriorAnio' => round($bolsaPosteriorAnio, 2),
            'salidasPendientesEstimadas' => round($pagos + $pagosSinVencimiento + $bolsaConIva, 2),
            'saldoForecastReal' => round($saldoInicialAnio + $cobros - $pagos, 2),
            'saldoProyectadoFinAnio' => round($saldo, 2),
            'meses' => $meses,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function crearMeses(\DateTimeImmutable $hoy, float $saldoActual): array
    {
        $nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $meses = [];
        foreach ($nombres as $indice => $nombre) {
            $meses[$indice + 1] = [
                'nombre' => $nombre,
                'cobrosForecast' => 0.0,
                'cobrosProyectoEstimados' => 0.0,
                'pagosForecast' => 0.0,
                'bolsaGastos' => 0.0,
                'pagosSinVencimiento' => 0.0,
                'saldoProyectado' => $saldoActual,
                'pasado' => $indice + 1 < (int) $hoy->format('n'),
            ];
        }
        return $meses;
    }
}
