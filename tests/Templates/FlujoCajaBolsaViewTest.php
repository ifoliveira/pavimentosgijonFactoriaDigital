<?php

namespace App\Tests\Templates;

use App\Entity\BolsaGastosConfiguracion;
use App\Entity\FacturaProveedor;
use App\Service\Dashboard\DashboardProyectoService;
use App\Service\FlujoCajaAnualService;
use App\Service\ForecastService;
use App\Service\Tesoreria\BolsaGastosService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

final class FlujoCajaBolsaViewTest extends KernelTestCase
{
    /** @dataProvider activaciones */
    public function testLaVistaMuestraLaPrevisionResidualSinLosCompromisosAntiguos(bool $activo): void
    {
        self::bootKernel();
        $config = new BolsaGastosConfiguracion();
        if ($activo) {
            $config->setFechaInicio(new \DateTimeImmutable('2027-01-01'));
        }
        $factura = (new FacturaProveedor())->setProveedorNombre('Proveedor')->setNumeroFactura('F1');
        (new \ReflectionProperty($factura, 'id'))->setValue($factura, 1);
        $bolsa = $this->createMock(BolsaGastosService::class);
        $bolsa->method('getResumen')->willReturn([
            'activo' => $activo, 'configuracion' => $config, 'saldoBase' => $activo ? 100 : 0,
            'saldoIva' => $activo ? 21 : 0, 'saldoConIva' => $activo ? 121 : 0, 'incidencias' => [],
            'pendientes' => $activo ? [['fechaPrevista' => new \DateTimeImmutable('2027-02-01'), 'restanteCentimos' => 10000, 'ivaCentimos' => 2100, 'totalCentimos' => 12100]] : [],
            'facturasSinVencimiento' => $activo ? [['factura' => $factura, 'fecha' => new \DateTimeImmutable('2027-01-20'), 'importe' => 60.5]] : [],
        ]);
        $forecast = $this->createMock(ForecastService::class);
        $forecast->method('getForecastPendiente')->willReturn(['list' => []]);
        $dashboard = $this->createMock(DashboardProyectoService::class);
        $dashboard->method('getCobrosPendientesProyectos')->willReturn([]);
        $anual = (new FlujoCajaAnualService($forecast, $dashboard, $bolsa))->calcular(1000, new \DateTimeImmutable('2027-01-15'));
        $twig = self::getContainer()->get(Environment::class);
        $twig->setLoader(new ChainLoader([new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']), $twig->getLoader()]));
        $html = $twig->render('admin_pro/indexpro.html.twig', [
            'resumenAnual' => $anual, 'bancototal' => ['sum(importe_bn)' => 1000, 'max(fecha_bn)' => '2027-01-15'],
            'gastos' => ['efectivototal' => 0], 'forecast' => [], 'forecastChartData' => [],
            'anio' => 2027, 'aniosDisponibles' => [2026, 2027, 2028],
        ]);
        self::assertStringContainsString('Configurar bolsa de gastos', $html);
        self::assertStringContainsString('Total previsto de pagos', $html);
        self::assertStringContainsString('id="anio-flujo-caja"', $html);
        self::assertStringContainsString('<option value="2027" selected>2027</option>', $html);
        self::assertStringContainsString('Previsión de tesorería del ejercicio 2027', $html);
        if ($activo) {
            self::assertStringContainsString('Ver desglose de la bolsa previsto para 2027', $html);
        }
        self::assertStringNotContainsString('Compromisos pendientes de obras', $html);
        if ($activo) {
            self::assertStringContainsString('181,50 €', $html);
            self::assertStringContainsString('Proveedor · F1', $html);
        } else {
            self::assertStringContainsString('La bolsa de gastos está sin activar', $html);
        }
    }

    public static function activaciones(): iterable
    {
        yield [false];
        yield [true];
    }
}
