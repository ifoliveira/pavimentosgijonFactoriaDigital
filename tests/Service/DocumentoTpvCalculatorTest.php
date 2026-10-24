<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Service\Documento\DocumentoCalculatorService;
use PHPUnit\Framework\TestCase;

final class DocumentoTpvCalculatorTest extends TestCase
{
    /** @dataProvider precios */
    public function testConservaElPvpConIvaAlRecalcular(string $pvp, string $cantidad, string $descuento, string $iva, string $total): void
    {
        $doc = new Documento();
        $linea = new DocumentoLinea();
        $linea->setPrecioUnitarioConIva($pvp)->setCantidad($cantidad)->setDescuento($descuento)->setTipoIva($iva);
        $doc->addLinea($linea);
        $calculator = new DocumentoCalculatorService();

        $calculator->recalcularDocumento($doc);
        self::assertSame($total, $doc->getTotal());
        self::assertSame($total, bcadd($doc->getBaseImponible(), $doc->getTotalIva(), 2));
        $calculator->recalcularDocumento($doc);
        self::assertSame($total, $doc->getTotal());
    }

    public function precios(): iterable
    {
        yield ['10.00', '2', '0', '21', '20.00'];
        yield ['0.10', '100', '0', '21', '10.00'];
        yield ['12.50', '3', '10', '21', '33.75'];
        yield ['5.50', '2', '0', '10', '11.00'];
        yield ['1.04', '1', '0', '4', '1.04'];
        yield ['5.00', '1', '0', '0', '5.00'];
        yield ['5.00', '1', '100', '21', '0.00'];
    }
}
