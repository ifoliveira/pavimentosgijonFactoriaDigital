<?php

namespace App\Tests\Service;

use App\Entity\Forecast;
use App\Service\Dashboard\DashboardProyectoService;
use App\Service\FlujoCajaAnualService;
use App\Service\ForecastService;
use App\Service\Tesoreria\BolsaGastosService;
use PHPUnit\Framework\TestCase;

class FlujoCajaBolsaTest extends TestCase
{
    public function testSumaSoloReservaResidualYVencimientosIncluidosLosAnterioresAlCorte(): void
    {
        $forecasts = $this->createMock(ForecastService::class);
        $forecasts->method('getForecastPendiente')->willReturn(['list' => [
            $this->forecast(-3630, '2027-02-10'),
            $this->forecast(-100, '2026-12-31'),
            $this->forecast(2000, '2027-02-01'),
            $this->forecast(-9999, '2028-01-01'),
        ]]);
        $dashboard = $this->createMock(DashboardProyectoService::class);
        $dashboard->expects(self::never())->method('getCompromisosPendientes');
        $dashboard->method('getCobrosPendientesProyectos')->willReturn([
            ['fecha' => new \DateTimeImmutable('2027-03-01'), 'importe' => 1000],
        ]);
        $bolsa = $this->createMock(BolsaGastosService::class);
        $bolsa->method('getResumen')->willReturn([
            'saldoConIva' => 9680,
            'pendientes' => [
                ['fechaPrevista' => new \DateTimeImmutable('2027-04-01'), 'restanteCentimos' => 700000, 'ivaCentimos' => 147000, 'totalCentimos' => 847000],
                ['fechaPrevista' => new \DateTimeImmutable('2028-02-01'), 'restanteCentimos' => 100000, 'ivaCentimos' => 21000, 'totalCentimos' => 121000],
            ],
            'facturasSinVencimiento' => [
                ['fecha' => new \DateTimeImmutable('2027-02-20'), 'importe' => 1210],
            ],
        ]);
        $resumen = (new FlujoCajaAnualService($forecasts, $dashboard, $bolsa))->calcular(20000, new \DateTimeImmutable('2027-01-15'));
        $diarios = array_column($resumen['gastosAcumulados'], 'y', 'x');
        self::assertCount(351, $diarios);
        self::assertEquals(100, $diarios['2027-01-15']);
        self::assertEquals(100, $diarios['2027-02-01']); // Los cobros no reducen el gasto.
        self::assertEquals(3730, $diarios['2027-02-10']);
        self::assertEquals(4940, $diarios['2027-02-20']);
        self::assertEquals(13410, $diarios['2027-04-01']);
        self::assertEquals($resumen['salidasPendientesEstimadas'], $diarios['2027-12-31']);
        self::assertEquals(3730, $resumen['pagosForecast']);
        self::assertEquals(8470, $resumen['bolsaConIva']);
        self::assertEquals(1210, $resumen['bolsaPosteriorAnio']);
        self::assertEquals(13410, $resumen['salidasPendientesEstimadas']);
        self::assertEquals(9590, $resumen['saldoProyectadoFinAnio']);
        self::assertEquals(100, $resumen['meses'][1]['pagosForecast']);
        self::assertEquals(1210, $resumen['meses'][2]['pagosSinVencimiento']);
        self::assertEquals(8470, $resumen['meses'][4]['bolsaGastos']);
        self::assertEquals($resumen['saldoProyectadoFinAnio'], $resumen['meses'][12]['saldoProyectado']);
    }

    public function testBolsaSinActivarNoOcultaVencimientosNiIncluyeCostesAntiguos(): void
    {
        $forecasts = $this->createMock(ForecastService::class);
        $forecasts->method('getForecastPendiente')->willReturn(['list' => [$this->forecast(-500, '2026-11-30')]]);
        $dashboard = $this->createMock(DashboardProyectoService::class);
        $dashboard->expects(self::never())->method('getCompromisosPendientes');
        $dashboard->method('getCobrosPendientesProyectos')->willReturn([]);
        $bolsa = $this->createMock(BolsaGastosService::class);
        $bolsa->method('getResumen')->willReturn(['saldoConIva' => 0, 'pendientes' => [], 'facturasSinVencimiento' => []]);
        $resumen = (new FlujoCajaAnualService($forecasts, $dashboard, $bolsa))->calcular(2000, new \DateTimeImmutable('2026-11-15'));
        self::assertEquals(500, $resumen['salidasPendientesEstimadas']);
        self::assertEquals(1500, $resumen['saldoProyectadoFinAnio']);
    }

    public function testSeleccionarElAnioSiguienteMuestraSoloSusForecasts(): void
    {
        $forecasts = $this->createMock(ForecastService::class);
        $forecasts->method('getForecastPendiente')->willReturn(['list' => [
            $this->forecast(-300, '2026-12-20'),
            $this->forecast(-500, '2027-02-10'),
            $this->forecast(800, '2027-03-15'),
            $this->forecast(-900, '2028-01-05'),
        ]]);
        $dashboard = $this->createMock(DashboardProyectoService::class);
        $dashboard->expects(self::once())->method('getCobrosPendientesProyectos')
            ->with(2027)->willReturn([]);
        $bolsa = $this->createMock(BolsaGastosService::class);
        $bolsa->method('getResumen')->willReturn([
            'saldoConIva' => 0,
            'pendientes' => [],
            'facturasSinVencimiento' => [],
        ]);

        $resumen = (new FlujoCajaAnualService($forecasts, $dashboard, $bolsa))
            ->calcular(2000, new \DateTimeImmutable('2026-11-15'), 2027);

        $diarios = array_column($resumen['gastosAcumulados'], 'y', 'x');
        self::assertCount(365, $diarios);
        self::assertEquals(0, $diarios['2027-01-01']);
        self::assertEquals(500, $diarios['2027-02-10']);
        self::assertEquals(500, $diarios['2027-12-31']);
        self::assertSame(2027, $resumen['anio']);
        self::assertSame('2027-01-01', $resumen['fechaCorte']->format('Y-m-d'));
        self::assertEquals(500, $resumen['pagosForecast']);
        self::assertEquals(800, $resumen['cobrosForecast']);
        self::assertEquals(1700, $resumen['saldoInicialAnio']);
        self::assertEquals(500, $resumen['meses'][2]['pagosForecast']);
        self::assertEquals(800, $resumen['meses'][3]['cobrosForecast']);
        self::assertEquals(2000, $resumen['saldoProyectadoFinAnio']);
    }

    public function testElSaldoDeBolsaDe2026SeDescuentaAntesDeAbrir2027(): void
    {
        $forecasts = $this->createMock(ForecastService::class);
        $forecasts->method('getForecastPendiente')->willReturn(['list' => [
            $this->forecast(-950.33, '2026-12-11'),
            $this->forecast(-119.32, '2026-12-11'),
        ]]);
        $dashboard = $this->createMock(DashboardProyectoService::class);
        $dashboard->method('getCobrosPendientesProyectos')->willReturn([]);
        $bolsa = $this->createMock(BolsaGastosService::class);
        $bolsa->method('getResumen')->willReturn([
            'saldoConIva' => 3072.42,
            'pendientes' => [
                ['fechaPrevista' => new \DateTimeImmutable('2026-12-27'), 'restanteCentimos' => 82759, 'ivaCentimos' => 17379, 'totalCentimos' => 100138],
                ['fechaPrevista' => new \DateTimeImmutable('2027-01-22'), 'restanteCentimos' => 171160, 'ivaCentimos' => 35944, 'totalCentimos' => 207104],
            ],
            'facturasSinVencimiento' => [],
        ]);

        $resumen = (new FlujoCajaAnualService($forecasts, $dashboard, $bolsa))
            ->calcular(4298.95, new \DateTimeImmutable('2026-11-15'), 2027);

        self::assertEquals(2227.92, $resumen['saldoInicialAnio']);
        self::assertEquals(2071.04, $resumen['bolsaConIva']);
        self::assertEquals(2071.04, $resumen['meses'][1]['bolsaGastos']);
        self::assertEquals(156.88, $resumen['meses'][1]['saldoProyectado']);
        self::assertEquals(156.88, $resumen['saldoProyectadoFinAnio']);
    }

    private function forecast(float $importe, string $fecha): Forecast
    {
        return (new Forecast())->setImporteFr($importe)->setFechaFr(new \DateTime($fecha));
    }
}
