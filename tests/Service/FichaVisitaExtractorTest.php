<?php

namespace App\Tests\Service;

use App\Service\FichaVisita\OpenAiFichaVisitaExtractor;
use App\Service\OpenAIClient;
use PHPUnit\Framework\TestCase;

final class FichaVisitaExtractorTest extends TestCase
{
    public function testMapsSimulatedAiResponse(): void
    {
        $openAI = $this->getMockBuilder(OpenAIClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['interpretarFichaVisitaImagen'])
            ->getMock();

        $openAI->method('interpretarFichaVisitaImagen')->willReturn([
            'nombre_cliente' => 'Ana Garcia',
            'telefono' => '600 111 222',
            'direccion_obra' => 'Calle Mayor 12',
            'pendientes' => [],
        ]);

        $extractor = new OpenAiFichaVisitaExtractor($openAI);
        $datos = $extractor->extraer(__FILE__, 'image/jpeg');

        self::assertSame('Ana Garcia', $datos->nombreCliente);
        self::assertSame('600 111 222', $datos->telefono);
        self::assertSame('Calle Mayor 12', $datos->direccionObra);
        self::assertSame([], $datos->pendientes);
    }
}
