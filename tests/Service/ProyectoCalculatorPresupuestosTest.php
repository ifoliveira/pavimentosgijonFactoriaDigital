<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Repository\DocumentoRepository;
use App\Service\Proyecto\ProyectoCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProyectoCalculatorPresupuestosTest extends TestCase
{
    public function testSumaSoloPresupuestosAceptadosYConservaLaFacturacionSeparada(): void
    {
        $proyecto = new Proyecto();

        $inicial = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado')->setTotal('6000.00');
        $descuento = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('convertido')->setTotal('-100.00');
        $adicional = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado')->setTotal('250.00');
        $pendiente = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('entregado')->setTotal('400.00');

        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findPresupuestosAceptadosDeProyecto')
            ->with($proyecto)
            ->willReturn([$inicial, $descuento, $adicional]);
        $documentos->method('findFacturaCompletaDeProyecto')->with($proyecto)->willReturn(null);
        $documentos->method('findTicketDeProyecto')->with($proyecto)->willReturn(null);

        $calculator = new ProyectoCalculatorService(
            $documentos,
            $this->createMock(EntityManagerInterface::class),
        );

        $calculator->recalcularProyecto($proyecto, false);

        self::assertSame('6150.00', $proyecto->getTotalPresupuestado());
        self::assertSame('0.00', $proyecto->getTotalFacturado());
        self::assertSame('0.00', $proyecto->getTotalCobrado());
        self::assertSame('400.00', $pendiente->getTotal());
    }

    public function testFacturaDeAnticipoNoReduceElImporteTotalDeLaObra(): void
    {
        $proyecto = new Proyecto();
        $presupuesto = (new Documento())
            ->setTipoDocumento('presupuesto')
            ->setEstadoComercial('aceptado')
            ->setTotal('12100.00');
        $anticipo = (new Documento())
            ->setTipoDocumento('factura')
            ->setTotal('1210.00')
            ->setTotalCobrado('1210.00');
        $cobro = (new ProyectoCobro())
            ->setImporteBruto('1210.00')
            ->setFacturaAnticipo($anticipo);
        $proyecto->addCobro($cobro);

        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findPresupuestosAceptadosDeProyecto')
            ->with($proyecto)
            ->willReturn([$presupuesto]);
        $documentos->method('findFacturaCompletaDeProyecto')->with($proyecto)->willReturn(null);
        $documentos->method('findTicketDeProyecto')->with($proyecto)->willReturn(null);

        $calculator = new ProyectoCalculatorService(
            $documentos,
            $this->createMock(EntityManagerInterface::class),
        );

        $calculator->recalcularProyecto($proyecto, false);

        self::assertSame('12100.00', $proyecto->getTotalPresupuestado());
        self::assertSame('1210.00', $proyecto->getTotalFacturado());
        self::assertSame('10890.00', $proyecto->getPendienteCobro());
        self::assertSame('1210.00', $proyecto->getTotalCobrado());
        self::assertNull($proyecto->getFechaFinReal());
    }
}
