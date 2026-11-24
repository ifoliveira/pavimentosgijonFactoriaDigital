<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Service\Documento\DocumentoCalculatorService;
use PHPUnit\Framework\TestCase;

class PresupuestoComplementarioCalculatorTest extends TestCase
{
    public function testDescuentoDeOchocientosYAltaDeSetecientosDanMenosCien(): void
    {
        $documento = (new Documento())->setTipoDocumento('presupuesto');

        foreach ([[-1, '800.00'], [1, '700.00']] as [$cantidad, $precio]) {
            $documento->addLinea((new DocumentoLinea())
                ->setCantidad((string) $cantidad)
                ->setPrecioUnitario($precio)
                ->setCosteUnitario('0.00')
                ->setDescuento('0.00')
                ->setTipoIva('0.00')
                ->setTotalCoste('0.00'));
        }

        (new DocumentoCalculatorService())->recalcularDocumento($documento);

        self::assertSame('-100.00', $documento->getBaseImponible());
        self::assertSame('-100.00', $documento->getTotal());
    }
}
