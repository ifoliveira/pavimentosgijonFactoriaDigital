<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Repository\DocumentoRepository;
use App\Service\Proyecto\ProyectoCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProyectoFinalizacionTest extends TestCase
{
    public function testCierreAntiguoPorCobroPermiteFinalizacionManual(): void
    {
        $proyecto = (new Proyecto())->setFechaFinReal(new \DateTimeImmutable('2020-01-01'));
        $proyecto->addDocumento((new Documento())->setTipoDocumento('factura')->setEstadoEjecucion('pendiente'));
        self::assertFalse($proyecto->isObraFinalizada());
        $proyecto->finalizarObra();
        self::assertTrue($proyecto->isObraFinalizada());
        self::assertSame((new \DateTimeImmutable())->format('Y-m-d'), $proyecto->getFechaFinReal()->format('Y-m-d'));
    }

    public function testFinalizacionManualConDeudaSeMantieneAlRecalcular(): void
    {
        $proyecto = new Proyecto();
        $factura = (new Documento())->setTipoDocumento('factura')->setTotal('2000.00');
        $anticipo = (new Documento())->setTipoDocumento('factura')->setTotal('1000.00')->setEstadoCobro('cobrado');
        $presupuesto = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado')->setTotal('3000.00');
        $proyecto->addDocumento($presupuesto)->addDocumento($factura)->addDocumento($anticipo);
        $proyecto->addCobro((new ProyectoCobro())->setImporteBruto('1000.00')->setFacturaAnticipo($anticipo));

        $proyecto->finalizarObra();
        $fecha = $proyecto->getFechaFinReal();
        self::assertNotNull($fecha);
        self::assertSame('completada', $factura->getEstadoEjecucion());
        self::assertSame('completada', $anticipo->getEstadoEjecucion());
        self::assertSame('pendiente', $factura->getEstadoCobro());
        self::assertSame('aceptado', $presupuesto->getEstadoComercial());

        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn($factura);
        $repo->method('findPresupuestosAceptadosDeProyecto')->willReturn([$presupuesto]);
        (new ProyectoCalculatorService($repo, $this->createMock(EntityManagerInterface::class)))->recalcularProyecto($proyecto, false);
        self::assertSame($fecha, $proyecto->getFechaFinReal());
        self::assertSame('2000.00', $proyecto->getPendienteCobro());
        $proyecto->finalizarObra();
        self::assertSame($fecha, $proyecto->getFechaFinReal());
    }

    public function testCobrarTodoNoFinalizaLaObra(): void
    {
        $proyecto = new Proyecto();
        $factura = (new Documento())->setTipoDocumento('factura')->setTotal('2000.00')->setEstadoEjecucion('pendiente');
        $proyecto->addDocumento($factura);
        $proyecto->addCobro((new ProyectoCobro())->setImporteBruto('2000.00'));
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn($factura);
        (new ProyectoCalculatorService($repo, $this->createMock(EntityManagerInterface::class)))->recalcularProyecto($proyecto, false);
        self::assertNull($proyecto->getFechaFinReal());
        self::assertSame('pendiente', $factura->getEstadoEjecucion());
        self::assertSame('cobrado', $factura->getEstadoCobro());
    }
}
