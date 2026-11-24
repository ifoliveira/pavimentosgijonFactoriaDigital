<?php

namespace App\Tests\Entity;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use PHPUnit\Framework\TestCase;

class ProyectoPendienteTest extends TestCase
{
    public function testCobroSinFacturaDescuentaDelPresupuesto(): void
    {
        $proyecto = (new Proyecto())
            ->setTotalPresupuestado('3069.88')
            ->setTotalFacturado('0.00')
            ->setTotalCobrado('1069.88');

        self::assertSame('3069.88', $proyecto->getTotalOperacion());
        self::assertSame('2000.00', $proyecto->getPendienteCobro());
        self::assertSame('2000.00', $proyecto->getPendiente());

        $proyecto->setTotalFacturado('1069.88');
        $proyecto->addCobro((new ProyectoCobro())->setFacturaAnticipo(new Documento()));
        self::assertSame('2000.00', $proyecto->getPendienteCobro());

        $proyecto->setTotalCobrado('3100.00');
        self::assertSame('0.00', $proyecto->getPendienteCobro());
    }

    public function testFacturaCompletaInferiorAlPresupuestoConservaSuImporte(): void
    {
        $proyecto = (new Proyecto())
            ->setTotalPresupuestado('3069.88')
            ->setTotalFacturado('2500.00')
            ->setTotalCobrado('1069.88');

        self::assertSame('2500.00', $proyecto->getTotalOperacion());
        self::assertSame('1430.12', $proyecto->getPendienteCobro());
    }
}
