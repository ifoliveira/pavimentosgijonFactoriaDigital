<?php

namespace App\Tests\Service;

use App\Service\Cliente\ClienteFormato;
use PHPUnit\Framework\TestCase;

final class ClienteFormatoTest extends TestCase
{
    public function testNormalizaNombresConAcentosYSeparadores(): void
    {
        self::assertSame('María José', ClienteFormato::texto('  MARÍA   jOSÉ  '));
        self::assertSame("Pérez-García D'Ávila", ClienteFormato::texto("PÉREZ-GARCÍA D'ÁVILA"));
    }

    public function testAgrupaTelefonosSinPerderCifras(): void
    {
        self::assertSame('600 123 456', ClienteFormato::telefono('600-123456'));
        self::assertSame('+346 001 234 56', ClienteFormato::telefono('+34 (600) 123456'));
        self::assertSame('600123456 ext. 12', ClienteFormato::telefono('600123456 ext. 12'));
        self::assertSame('', ClienteFormato::telefono(''));
    }

    public function testNormalizaViasSinDuplicarlas(): void
    {
        foreach ([
            ['C/CORRIDA, 12', 'Calle', 'Calle Corrida, 12'],
            ['avda. DE LA COSTA 25', 'Calle', 'Avenida De La Costa 25'],
            ['PZA. MAYOR 3', 'Calle', 'Plaza Mayor 3'],
            ['LOS ROBLES 8', 'Camino', 'Camino Los Robles 8'],
            ['  ', 'Calle', ''],
            ['Calle Corrida, 12', 'Calle', 'Calle Corrida, 12'],
        ] as [$value, $via, $expected]) {
            self::assertSame($expected, ClienteFormato::direccion($value, $via));
            self::assertSame($expected, ClienteFormato::direccion($expected, $via));
        }
    }
}
