<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;
use App\Service\Dashboard\DashboardProyectoService;
use PHPUnit\Framework\TestCase;

class DashboardProyectoMargenTest extends TestCase
{
    public function testMargenExcluyeIvaConTiposMixtosYRectificacion(): void
    {
        $proyecto = (new Proyecto())->setTotalFacturado('1294.50')->setTotalCobrado('200.00');
        $presupuesto = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('convertido')->setBaseImponible('1500.00');
        $factura = (new Documento())->setTipoDocumento('factura')->setBaseImponible('1000.00')->setTotal('1100.00');
        $proyecto->addDocumento($presupuesto);
        $proyecto->addDocumento($factura);
        $proyecto->addDocumento((new Documento())->setTipoDocumento('ticket')->setBaseImponible('200.00')->setTotal('242.00'));
        $proyecto->addDocumento((new Documento())->setTipoDocumento('ticket_rectificativo')->setBaseImponible('-50.00')->setTotal('-60.50'));
        $proyecto->addGasto((new ProyectoGasto())->setImportePrevisto('484.00')->setBasePrevista('400.00'));
        $proyecto->addGasto((new ProyectoGasto())->setImportePrevisto('50.00')->setBasePrevista('50.00')->setImporteReal('121.00')->setIvaReal('21.00'));
        $proyecto->addGasto((new ProyectoGasto())->setImportePrevisto('60.50')->setIvaPrevisto('10.50'));
        $proyecto->addGasto((new ProyectoGasto())->setImportePrevisto('25.00'));
        $proyecto->addGasto((new ProyectoGasto())->setImporteReal('121.00')->setBaseReal('100.00')->setIvaDeducible(false));
        $proyecto->addGasto((new ProyectoGasto())->setImportePrevisto('900.00')->setEstado('cancelado'));

        $data = $this->servicio($proyecto, $presupuesto, $factura)->getDashboardData();

        self::assertEquals(675.0, $data['economico']['totalCostePrevisto']);
        self::assertEquals(475.0, $data['economico']['margenEstimado']);
        self::assertEquals(475.0, $data['ultimosProyectos'][0]['margen']);
        self::assertEquals(1094.5, $data['economico']['totalPendienteCobro']);
    }

    public function testPresupuestoAceptadoSinFacturaUsaBaseYNoDuplicaIngresos(): void
    {
        $proyecto = (new Proyecto())->setTotalFacturado('1210.00');
        $presupuesto = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado')->setBaseImponible('1000.00');
        $proyecto->addDocumento($presupuesto);
        $service = $this->servicio($proyecto, $presupuesto, null);
        self::assertEquals(1000.0, $service->getDashboardData()['economico']['margenEstimado']);
        $presupuesto->setEstadoComercial('borrador');
        self::assertEquals(0.0, $service->getDashboardData()['economico']['margenEstimado']);
    }

    public function testCobrosPendientesSoloIncluyePresupuestosAceptadosYNoInflaLaFactura(): void
    {
        $aceptado = (new Proyecto())
            ->setFechaInicio(new \DateTimeImmutable('2026-01-01'))
            ->setTotalPresupuestado('1200.00')
            ->setTotalFacturado('800.00')
            ->setTotalCobrado('100.00');
        $borrador = (new Proyecto())
            ->setFechaInicio(new \DateTimeImmutable('2026-01-01'))
            ->setTotalPresupuestado('5000.00')
            ->setTotalFacturado('0.00')
            ->setTotalCobrado('0.00');

        $presupuestoAceptado = (new Documento())->setEstadoComercial('aceptado');
        $presupuestoBorrador = (new Documento())->setEstadoComercial('borrador');
        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('findAll')->willReturn([$aceptado, $borrador]);
        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findPresupuestoInicialDeProyecto')->willReturnCallback(
            static fn (Proyecto $proyecto): Documento => $proyecto === $aceptado ? $presupuestoAceptado : $presupuestoBorrador
        );

        $service = new DashboardProyectoService($proyectos, $documentos);

        $cobros = $service->getCobrosPendientesProyectos(2026);
        self::assertCount(1, $cobros);
        self::assertSame($aceptado, $cobros[0]['proyecto']);
        self::assertSame(700.0, $cobros[0]['importe']);
    }

    private function servicio(Proyecto $proyecto, Documento $presupuesto, ?Documento $factura): DashboardProyectoService
    {
        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('findAll')->willReturn([$proyecto]);
        $proyectos->method('findUltimosProyectosDashboard')->willReturn([$proyecto]);
        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findPresupuestoInicialDeProyecto')->willReturn($presupuesto);
        $documentos->method('findFacturaDeProyecto')->willReturn($factura);
        return new DashboardProyectoService($proyectos, $documentos);
    }
}
