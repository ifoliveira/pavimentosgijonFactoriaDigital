<?php

namespace App\Tests\Integration\BudgetFlow;

use App\Integration\BudgetFlow\BudgetFlowClient;
use App\Integration\BudgetFlow\BudgetFlowException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BudgetFlowClientTest extends TestCase
{
    public function testRespuesta200DevuelveArray(): void
    {
        $client = new BudgetFlowClient(new MockHttpClient([
            new MockResponse('{"ok":true,"lineas":[]}', ['http_code' => 200]),
        ]));

        self::assertSame(['ok' => true, 'lineas' => []], $client->generar('reforma_bano', ['foo' => 'bar']));
    }

    public function testObtenerDefinicionUsaEndpointNuevo(): void
    {
        $client = new BudgetFlowClient(new MockHttpClient(static function (string $method, string $url): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringEndsWith('/api/v1/configuradores/web_ba%C3%B1o/definicion', $url);

            return new MockResponse('{"codigo":"web_baño","componentes":[]}', ['http_code' => 200]);
        }));

        self::assertSame(
            ['codigo' => 'web_baño', 'componentes' => []],
            $client->obtenerDefinicion('web_baño')
        );
    }

    public function testRespuesta400RegistraContextoYLanzaMensajeUtil(): void
    {
        $logger = new ArrayLogger();
        $client = new BudgetFlowClient(new MockHttpClient([
            new MockResponse('{"error":"Campo obligatorio: largo"}', ['http_code' => 400]),
        ]), $logger);

        try {
            $client->validar('reforma_bano', ['ancho' => 70]);
            self::fail('Se esperaba BudgetFlowException.');
        } catch (BudgetFlowException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertSame('/api/v1/configuradores/reforma_bano/validar', $e->endpoint);
            self::assertSame(['ancho' => 70], $e->payload);
            self::assertStringContainsString('BudgetFlow respondió HTTP 400', $e->getMessage());
            self::assertStringContainsString('Campo obligatorio: largo', $e->getMessage());
            self::assertSame('{"error":"Campo obligatorio: largo"}', $e->responseBody);
        }

        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame(400, $logger->records[0]['context']['status']);
        self::assertSame(['ancho' => 70], $logger->records[0]['context']['payload']);
        self::assertSame('{"error":"Campo obligatorio: largo"}', $logger->records[0]['context']['response_body']);
    }

    public function testRespuesta500IncluyeRespuestaReal(): void
    {
        $client = new BudgetFlowClient(new MockHttpClient([
            new MockResponse('Internal BudgetFlow trace 123', ['http_code' => 500]),
        ]));

        $this->expectException(BudgetFlowException::class);
        $this->expectExceptionMessage('BudgetFlow respondió HTTP 500');
        $this->expectExceptionMessage('Internal BudgetFlow trace 123');

        $client->generar('reforma_bano', ['foo' => 'bar']);
    }

    public function testJsonInvalidoLanzaExcepcionConCuerpo(): void
    {
        $logger = new ArrayLogger();
        $client = new BudgetFlowClient(new MockHttpClient([
            new MockResponse('<html>no json</html>', ['http_code' => 200]),
        ]), $logger);

        $this->expectException(BudgetFlowException::class);
        $this->expectExceptionMessage('BudgetFlow devolvió una respuesta no JSON');
        $this->expectExceptionMessage('<html>no json</html>');

        try {
            $client->listarConfiguradores();
        } finally {
            self::assertSame('BudgetFlow devolvió una respuesta no JSON.', $logger->records[0]['message']);
        }
    }

    public function testErrorDeTransporteConservaCausaOriginal(): void
    {
        $transportException = new TransportException('Connection refused');
        $client = new BudgetFlowClient(new MockHttpClient(static function () use ($transportException): never {
            throw $transportException;
        }));

        try {
            $client->obtenerConfigurador('reforma_bano');
            self::fail('Se esperaba BudgetFlowException.');
        } catch (BudgetFlowException $e) {
            self::assertSame($transportException, $e->getPrevious());
            self::assertStringContainsString('Error de transporte llamando a BudgetFlow', $e->getMessage());
            self::assertStringContainsString('Connection refused', $e->getMessage());
            self::assertSame('/api/v1/configuradores/reforma_bano', $e->endpoint);
        }
    }
}

final class ArrayLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
