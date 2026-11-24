<?php

namespace App\Tests\Entity;

use App\Entity\Documento;
use App\Entity\Proyecto;
use PHPUnit\Framework\TestCase;

final class ProyectoRechazadoTest extends TestCase
{
    public function testSeRechazaAlRechazarElUltimoYSeRecuperaConUnoVigente(): void
    {
        $proyecto = new Proyecto();
        self::assertFalse($proyecto->isRechazado());
        $primero = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('entregado');
        $segundo = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('borrador');
        $proyecto->addDocumento($primero)->addDocumento($segundo);
        $primero->setEstadoComercial('rechazado');
        self::assertFalse($proyecto->isRechazado());
        $segundo->setEstadoComercial('rechazado');
        self::assertTrue($proyecto->isRechazado());
        $primero->setEstadoComercial('entregado');
        self::assertFalse($proyecto->isRechazado());
        $primero->setEstadoComercial('rechazado');
        $proyecto->addDocumento((new Documento())->setTipoDocumento('presupuesto'));
        self::assertFalse($proyecto->isRechazado());
    }

    /** @dataProvider estadosVigentes */
    public function testCualquierPresupuestoVigenteImpideElRechazo(string $estado): void
    {
        $proyecto = new Proyecto();
        $proyecto->addDocumento((new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('rechazado'));
        $proyecto->addDocumento((new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial($estado));
        self::assertFalse($proyecto->isRechazado());
    }

    public function estadosVigentes(): iterable
    {
        foreach (['borrador', 'entregado', 'enviado', 'aceptado', 'convertido', 'aplicado'] as $estado) {
            yield [$estado];
        }
    }

    public function testOtrosDocumentosNoSonPresupuestos(): void
    {
        $proyecto = new Proyecto();
        $proyecto->addDocumento((new Documento())->setTipoDocumento('factura')->setEstadoComercial('rechazado'));
        self::assertFalse($proyecto->isRechazado());
    }
}
