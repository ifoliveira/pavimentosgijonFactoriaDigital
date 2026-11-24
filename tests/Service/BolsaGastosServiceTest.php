<?php

namespace App\Tests\Service;

use App\Entity\BolsaGastosAjuste;
use App\Entity\BolsaGastosConfiguracion;
use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\FacturaProveedor;
use App\Entity\Forecast;
use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Repository\BolsaGastosAjusteRepository;
use App\Repository\BolsaGastosConfiguracionRepository;
use App\Repository\BolsaGastosOrigenRepository;
use App\Service\Tesoreria\BolsaGastosCalculator;
use App\Service\Tesoreria\BolsaGastosService;
use PHPUnit\Framework\TestCase;

class BolsaGastosServiceTest extends TestCase
{
    public function testNoSeActivaNiConsultaOrigenesSinFechaElegida(): void
    {
        foreach ([null, new \DateTimeImmutable('2028-01-01')] as $fecha) {
            $config = (new BolsaGastosConfiguracion())->setFechaInicio($fecha);
            $repo = $this->createMock(BolsaGastosConfiguracionRepository::class);
            $repo->method('obtener')->willReturn($config);
            $origen = $this->createMock(BolsaGastosOrigenRepository::class);
            $origen->expects(self::never())->method('findPresupuestos');
            $origen->expects(self::never())->method('findFacturas');
            $ajustes = $this->createMock(BolsaGastosAjusteRepository::class);
            $ajustes->expects(self::never())->method('findHasta');
            $resumen = (new BolsaGastosService($repo, $origen, $ajustes, new BolsaGastosCalculator()))->getResumen(new \DateTimeImmutable('2027-01-31'));
            self::assertFalse($resumen['activo']);
            self::assertEquals(0, $resumen['saldoBase']);
        }
    }

    public function testAsignarPagarYFinalizarLaObraNoVuelvenADescontarLaBolsa(): void
    {
        $presupuesto = $this->presupuesto(1, '2027-01-01', '10000.00');
        $factura = $this->factura(1, '2027-01-10', 3000);
        $forecast = (new Forecast())->setImporteFr(-3630)->setFechaFr(new \DateTime('2027-02-10'));
        $factura->addForecast($forecast);
        $service = $this->service([$presupuesto], [$factura]);
        $antes = $service->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(7000, $antes['saldoBase']);
        self::assertEquals(8470, $antes['saldoConIva']);
        self::assertSame([], $antes['facturasSinVencimiento']);

        $gasto = (new ProyectoGasto())->setDocumento($presupuesto)->setImportePrevisto('10000.00')->setImporteReal('3000.00')->setEstado('confirmado');
        $presupuesto->getProyecto()->addGasto($gasto);
        $presupuesto->getProyecto()->setFechaFinReal(new \DateTime('2027-01-20'));
        $factura->setEstadoAsignacion('asignada');
        $forecast->setEstadoFr('C');
        $despues = $service->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals($antes['saldoBase'], $despues['saldoBase']);
        self::assertEquals($antes['consumidoBase'], $despues['consumidoBase']);
        self::assertSame([], $despues['facturasSinVencimiento']);
    }

    public function testFacturasSinAsignarNiVencimientosConsumenYConservanSuPago(): void
    {
        $factura = $this->factura(1, '2027-01-10', 3000)->setEstadoAsignacion('revision');
        $service = $this->service([$this->presupuesto(1, '2027-01-01', '10000')], [$factura]);
        $resumen = $service->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(7000, $resumen['saldoBase']);
        self::assertEquals(3630, $resumen['facturasSinVencimiento'][0]['importe']);
        self::assertSame('2027-02-09', $resumen['facturasSinVencimiento'][0]['fecha']->format('Y-m-d'));

        $factura->addForecast((new Forecast())->setImporteFr(-1000)->setEstadoFr('C'));
        $factura->addForecast((new Forecast())->setImporteFr(-500));
        $parcial = $service->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(2130, $parcial['facturasSinVencimiento'][0]['importe']);
        self::assertEquals(7000, $parcial['saldoBase']);
        $factura->addForecast((new Forecast())->setImporteFr(-2130));
        self::assertSame([], $service->getResumen(new \DateTimeImmutable('2027-01-31'))['facturasSinVencimiento']);
    }

    public function testExclusionPorProveedorNoOcultaPagosYNormalizaElNombre(): void
    {
        $config = $this->config()->setProveedoresExcluidos(['  Energía   Local  ']);
        $factura = $this->factura(1, '2027-01-10', 100)->setProveedorNombre('ENERGIA local');
        $resumen = $this->service([$this->presupuesto(1, '2027-01-01', '1000')], [$factura], [], $config)
            ->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(1000, $resumen['saldoBase']);
        self::assertSame(1, $resumen['facturasExcluidas']);
        self::assertEquals(121, $resumen['facturasSinVencimiento'][0]['importe']);
    }

    public function testCorteIncluyeElPrimerDiaYExcluyeHistoriaYFuturo(): void
    {
        $ajusteAnterior = $this->id((new BolsaGastosAjuste())->setFecha(new \DateTimeImmutable('2026-12-31'))->setImporteBase('1000')->setMotivo('Anterior'), 1);
        $ajusteFuturo = $this->id((new BolsaGastosAjuste())->setFecha(new \DateTimeImmutable('2027-02-01'))->setImporteBase('1000')->setMotivo('Futuro'), 2);
        $service = $this->service([
            $this->presupuesto(1, '2026-12-31', '9000'),
            $this->presupuesto(2, '2027-01-01', '1000'),
            $this->presupuesto(3, '2027-02-01', '9000'),
        ], [
            $this->factura(1, '2026-12-31', 8000),
            $this->factura(2, '2027-01-01', 200),
            $this->factura(3, '2027-02-01', 8000),
        ], [$ajusteAnterior, $ajusteFuturo]);
        $resumen = $service->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(800, $resumen['saldoBase']);
        self::assertCount(2, $resumen['movimientos']);
        self::assertCount(1, $resumen['facturasSinVencimiento']);
    }

    public function testPlazosConfiguradosYCentimosNoDependenDelCatalogo(): void
    {
        $presupuesto = $this->presupuesto(1, '2027-01-01', '12.34');
        $presupuesto->getLineas()->first()->setCantidad('2.500');
        $config = $this->config()->setPlazoObraDias(10)->setPlazoPagoDias(20)->setIvaEstimado('10');
        $resumen = $this->service([$presupuesto], [], [], $config)->getResumen(new \DateTimeImmutable('2027-01-01'));
        self::assertEquals(30.85, $resumen['saldoBase']);
        self::assertEquals(3.09, $resumen['saldoIva']);
        self::assertSame('2027-01-31', $resumen['pendientes'][0]['fechaPrevista']->format('Y-m-d'));
        $presupuesto->getProyecto()->setFechaFinPrevista(new \DateTime('2027-12-20'));
        $resumen = $this->service([$presupuesto], [], [], $config)->getResumen(new \DateTimeImmutable('2027-01-01'));
        self::assertSame('2028-01-09', $resumen['pendientes'][0]['fechaPrevista']->format('Y-m-d'));
    }

    public function testAbonoYBaseInvalidaSeMuestranSinReponerLaReserva(): void
    {
        $factura = $this->factura(3, '2027-01-15', 100)->setTotalBase(null);
        $resumen = $this->service([$this->presupuesto(1, '2027-01-01', '1000')], [
            $this->factura(1, '2027-01-10', 300), $this->factura(2, '2027-01-11', -100), $factura,
            $this->factura(4, '2027-01-16', 100)->setTotalFactura(null),
        ])->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(700, $resumen['saldoBase']);
        self::assertCount(3, $resumen['incidencias']);
    }

    private function config(): BolsaGastosConfiguracion
    {
        return (new BolsaGastosConfiguracion())->setFechaInicio(new \DateTimeImmutable('2027-01-01'));
    }

    private function service(array $presupuestos, array $facturas, array $ajustes = [], ?BolsaGastosConfiguracion $config = null): BolsaGastosService
    {
        $repo = $this->createMock(BolsaGastosConfiguracionRepository::class);
        $repo->method('obtener')->willReturn($config ?? $this->config());
        $origen = $this->createMock(BolsaGastosOrigenRepository::class);
        $origen->method('findPresupuestos')->willReturn($presupuestos);
        $origen->method('findFacturas')->willReturn($facturas);
        $ajustesRepo = $this->createMock(BolsaGastosAjusteRepository::class);
        $ajustesRepo->method('findHasta')->willReturn($ajustes);
        return new BolsaGastosService($repo, $origen, $ajustesRepo, new BolsaGastosCalculator());
    }

    private function presupuesto(int $id, string $fecha, string $coste): Documento
    {
        $doc = (new Documento())->setTipoDocumento('presupuesto')->setSerie('P2027')->setNumero($id)
            ->setEstadoComercial('aceptado')->setFechaAceptacion(new \DateTime($fecha))->setProyecto((new Proyecto())->setNombre('Obra'));
        $doc->addLinea((new DocumentoLinea())->setCosteUnitario($coste)->setCantidad('1.000'));
        return $this->id($doc, $id);
    }

    private function factura(int $id, string $fecha, float $base): FacturaProveedor
    {
        return $this->id((new FacturaProveedor())->setFechaFactura(new \DateTime($fecha))->setTotalBase($base)
            ->setTotalFactura(round($base * 1.21, 2))->setProveedorNombre('Materiales')->setNumeroFactura('F'.$id), $id);
    }

    private function id(object $objeto, int $id): object
    {
        (new \ReflectionProperty($objeto, 'id'))->setValue($objeto, $id);
        return $objeto;
    }
}
