<?php

namespace App\Tests\Service;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebInteraccion;
use App\Entity\PresupuestoWebLead;
use App\Enum\EstadoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebInteraccion;
use App\Service\TelegramNotifierService;
use App\Service\WebPresupuesto\PresupuestoWebTelegramNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PresupuestoWebTelegramNotifierTest extends TestCase
{
    public function testMensajeDeMedicionIncluyeDatosClaveOrigenYEnlace(): void
    {
        $presupuesto = $this->presupuesto(25, '2946.96');
        $presupuesto->registrarVisualizacion(new \DateTimeImmutable('2026-09-19 10:30:00', new \DateTimeZone('Europe/Madrid')));
        $presupuesto->registrarVisualizacion(new \DateTimeImmutable('2026-09-19 12:35:00', new \DateTimeZone('Europe/Madrid')));
        $comunicacion = new PresupuestoWebComunicacion(
            $presupuesto,
            TipoPresupuestoWebComunicacion::SEGUIMIENTO,
            EstadoPresupuestoWebComunicacion::ENVIADA
        );
        $interaccion = new PresupuestoWebInteraccion(
            $presupuesto,
            $comunicacion,
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION,
            [
                'telefono' => '666 123 123',
                'direccion' => 'C/ ejemplo 10, 3º A, Gijón',
                'franja' => 'tarde',
                'observaciones' => 'El portal tiene ascensor.',
            ],
            new \DateTimeImmutable('2026-09-19 12:40:00', new \DateTimeZone('Europe/Madrid'))
        );

        $mensaje = $this->notifier()->mensaje($interaccion);

        self::assertStringContainsString('🔥 QUIERE QUE VAYAMOS A MEDIR', $mensaje);
        self::assertStringContainsString('Cambio bañera → ducha', $mensaje);
        self::assertStringContainsString('Importe: 2.946,96 €', $mensaje);
        self::assertStringContainsString('Fecha/hora: 19/09/2026 12:40', $mensaje);
        self::assertStringContainsString('Email: cliente@example.com', $mensaje);
        self::assertStringContainsString('Teléfono: 666 123 123', $mensaje);
        self::assertStringContainsString('C/ ejemplo 10, 3º A, Gijón', $mensaje);
        self::assertStringContainsString('Preferencia: tarde', $mensaje);
        self::assertStringContainsString('"El portal tiene ascensor."', $mensaje);
        self::assertStringContainsString('Ha consultado el presupuesto 2 veces.', $mensaje);
        self::assertStringContainsString('Última consulta: 19/09/2026 12:35', $mensaje);
        self::assertStringContainsString('Origen: seguimiento', $mensaje);
        self::assertStringContainsString('Abrir lead en ERP:', $mensaje);
        self::assertStringContainsString('https://pavimentos.test/admin/leads-web/'.$presupuesto->getId(), $mensaje);
    }

    public function testNoContinuaNoEnviaTelegram(): void
    {
        $telegram = $this->createMock(TelegramNotifierService::class);
        $telegram->expects(self::never())->method('sendMessage');
        $notifier = new PresupuestoWebTelegramNotifier($telegram, $this->urlGenerator('https://pavimentos.test/admin/leads-web/id'));
        $interaccion = new PresupuestoWebInteraccion(
            $this->presupuesto(25, '100.00'),
            null,
            TipoPresupuestoWebInteraccion::NO_CONTINUA
        );

        $notifier->notificar($interaccion);
    }

    private function notifier(): PresupuestoWebTelegramNotifier
    {
        return new PresupuestoWebTelegramNotifier(
            $this->createMock(TelegramNotifierService::class),
            $this->urlGenerator('https://pavimentos.test/admin/leads-web/id')
        );
    }

    private function urlGenerator(string $url): UrlGeneratorInterface
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(static function (string $route, array $parameters) use ($url): string {
            return str_replace('id', (string) ($parameters['id'] ?? 'id'), $url);
        });

        return $generator;
    }

    private function presupuesto(int $id, string $total): PresupuestoWeb
    {
        $lead = new PresupuestoWebLead('cliente@example.com', '2026-09-19');
        $presupuesto = new PresupuestoWeb($lead, 'ducha', bin2hex(random_bytes(32)), ['configurador' => 'web_ducha'], ['lineas' => []], $total);
        $this->setId($presupuesto, $id);

        return $presupuesto;
    }

    private function setId(object $object, int $id): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($object, $id);
    }
}
