<?php

namespace App\Tests\Service;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebInteraccion;
use App\Entity\PresupuestoWebLead;
use App\Enum\EstadoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebInteraccion;
use App\Repository\PresupuestoWebComunicacionRepository;
use App\Service\WebPresupuesto\PresupuestoWebInteraccionService;
use App\Service\WebPresupuesto\PresupuestoWebTelegramNotifier;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PresupuestoWebInteraccionServiceTest extends TestCase
{
    public function testDudaCreaInteraccionSinDetenerSeguimiento(): void
    {
        $presupuesto = $this->presupuesto(25);
        [$service, $persistidos, $notifier] = $this->service();

        $resultado = $service->registrar($presupuesto, [
            'tipo' => 'duda',
            'datos' => ['mensaje' => ' ¿Qué incluye? '],
        ]);

        self::assertTrue($resultado['ok']);
        self::assertFalse($resultado['seguimientoDetenido']);
        self::assertTrue($presupuesto->getLead()->isSeguimientoActivo());
        self::assertCount(1, $persistidos);
        self::assertInstanceOf(PresupuestoWebInteraccion::class, $persistidos[0]);
        self::assertSame(TipoPresupuestoWebInteraccion::DUDA, $persistidos[0]->getTipo());
        self::assertSame(['mensaje' => '¿Qué incluye?'], $persistidos[0]->getDatos());
        self::assertSame([$persistidos[0]], $notifier->notificaciones);
    }

    public function testCambioRegistraComponente(): void
    {
        [$service, $persistidos] = $this->service();

        $resultado = $service->registrar($this->presupuesto(25), [
            'tipo' => 'cambio',
            'datos' => ['componente' => 'mampara'],
        ]);

        self::assertTrue($resultado['ok']);
        self::assertSame(TipoPresupuestoWebInteraccion::CAMBIO, $persistidos[0]->getTipo());
        self::assertSame(['componente' => 'mampara'], $persistidos[0]->getDatos());
    }

    public function testContactoDetieneSeguimientoYCancelaPendientes(): void
    {
        $presupuesto = $this->presupuesto(25);
        $pendiente = $this->comunicacion(8, $presupuesto, EstadoPresupuestoWebComunicacion::PENDIENTE);
        [$service, $persistidos] = $this->service(pendientes: [$pendiente]);

        $resultado = $service->registrar($presupuesto, [
            'tipo' => 'solicita_contacto',
            'datos' => [
                'telefono' => '600 123 123',
                'franja' => 'tarde',
                'mensaje' => 'Mejor a partir de las cinco',
            ],
        ]);

        self::assertTrue($resultado['ok']);
        self::assertTrue($resultado['seguimientoDetenido']);
        self::assertSame(1, $resultado['comunicacionesCanceladas']);
        self::assertFalse($presupuesto->getLead()->isSeguimientoActivo());
        self::assertSame(EstadoPresupuestoWebComunicacion::CANCELADA, $pendiente->getEstado());
        self::assertSame(TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO, $persistidos[0]->getTipo());
        self::assertSame([
            'telefono' => '600 123 123',
            'franja' => 'tarde',
            'mensaje' => 'Mejor a partir de las cinco',
        ], $persistidos[0]->getDatos());
    }

    public function testMedicionExigeDireccion(): void
    {
        [$service, $persistidos] = $this->service();

        $resultado = $service->registrar($this->presupuesto(25), [
            'tipo' => 'solicita_medicion',
            'datos' => [
                'telefono' => '600123123',
                'franja' => 'manana',
            ],
        ]);

        self::assertFalse($resultado['ok']);
        self::assertSame('Indica la dirección donde se realizará la obra.', $resultado['error']);
        self::assertCount(0, $persistidos);
    }

    public function testNoContinuaDetieneSeguimientoYCancelaPendientes(): void
    {
        $presupuesto = $this->presupuesto(25);
        $pendienteA = $this->comunicacion(8, $presupuesto, EstadoPresupuestoWebComunicacion::PENDIENTE);
        $pendienteB = $this->comunicacion(9, $presupuesto, EstadoPresupuestoWebComunicacion::PENDIENTE);
        [$service, $persistidos, $notifier] = $this->service(pendientes: [$pendienteA, $pendienteB]);

        $resultado = $service->registrar($presupuesto, ['tipo' => 'no_continua']);

        self::assertTrue($resultado['ok']);
        self::assertFalse($presupuesto->getLead()->isSeguimientoActivo());
        self::assertSame(2, $resultado['comunicacionesCanceladas']);
        self::assertSame(EstadoPresupuestoWebComunicacion::CANCELADA, $pendienteA->getEstado());
        self::assertSame(EstadoPresupuestoWebComunicacion::CANCELADA, $pendienteB->getEstado());
        self::assertSame(TipoPresupuestoWebInteraccion::NO_CONTINUA, $persistidos[0]->getTipo());
        self::assertNull($persistidos[0]->getDatos());
        self::assertSame([], $notifier->notificaciones);
    }

    public function testAsociaComunicacionOrigenSoloSiPerteneceAlPresupuesto(): void
    {
        $presupuesto = $this->presupuesto(25);
        $comunicacion = $this->comunicacion(7, $presupuesto, EstadoPresupuestoWebComunicacion::ENVIADA);
        [$service, $persistidos] = $this->service(comunicaciones: ['token-a' => $comunicacion]);

        $resultado = $service->registrar($presupuesto, [
            'tipo' => 'duda',
            'comunicacionToken' => 'token-a',
            'datos' => ['mensaje' => 'Tengo una duda'],
        ]);

        self::assertTrue($resultado['ok']);
        self::assertSame($comunicacion, $persistidos[0]->getComunicacionOrigen());
    }

    public function testIgnoraComunicacionOrigenDeOtroPresupuesto(): void
    {
        $presupuesto = $this->presupuesto(25);
        $otra = $this->comunicacion(7, $this->presupuesto(26), EstadoPresupuestoWebComunicacion::ENVIADA);
        [$service, $persistidos] = $this->service(comunicaciones: ['token-ajeno' => $otra]);

        $resultado = $service->registrar($presupuesto, [
            'tipo' => 'duda',
            'comunicacionToken' => 'token-ajeno',
            'datos' => ['mensaje' => 'Tengo una duda'],
        ]);

        self::assertTrue($resultado['ok']);
        self::assertNull($persistidos[0]->getComunicacionOrigen());
    }

    public function testFalloDeTelegramNoRompeLaInteraccion(): void
    {
        $notifier = new FakePresupuestoWebTelegramNotifier();
        $notifier->fallar = true;
        [$service, $persistidos] = $this->service(notifier: $notifier);

        $resultado = $service->registrar($this->presupuesto(25), [
            'tipo' => 'duda',
            'datos' => ['mensaje' => 'Tengo una duda'],
        ]);

        self::assertTrue($resultado['ok']);
        self::assertCount(1, $persistidos);
    }

    private function service(array $comunicaciones = [], array $pendientes = [], ?FakePresupuestoWebTelegramNotifier $notifier = null): array
    {
        $persistidos = new \ArrayObject();
        $notifier ??= new FakePresupuestoWebTelegramNotifier();
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn(callable $callback): mixed => $callback());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($persistidos): void {
            $persistidos->append($entity);
        });

        $repo = $this->createMock(PresupuestoWebComunicacionRepository::class);
        $repo->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($comunicaciones): ?PresupuestoWebComunicacion {
            return $comunicaciones[$criteria['tokenAcceso'] ?? ''] ?? null;
        });
        $repo->method('findPendientesParaPresupuesto')->willReturn($pendientes);

        return [
            new PresupuestoWebInteraccionService($em, $repo, $notifier, $this->createMock(LoggerInterface::class)),
            $persistidos,
            $notifier,
        ];
    }

    private function presupuesto(int $id): PresupuestoWeb
    {
        $lead = new PresupuestoWebLead('cliente@example.com', '2026-09-19');
        $presupuesto = new PresupuestoWeb($lead, 'ducha', bin2hex(random_bytes(32)), ['configurador' => 'web_ducha'], ['lineas' => []], '100.00');
        $this->setId($presupuesto, $id);

        return $presupuesto;
    }

    private function comunicacion(int $id, PresupuestoWeb $presupuesto, EstadoPresupuestoWebComunicacion $estado): PresupuestoWebComunicacion
    {
        $comunicacion = new PresupuestoWebComunicacion(
            $presupuesto,
            TipoPresupuestoWebComunicacion::PRESUPUESTO,
            $estado
        );
        $this->setId($comunicacion, $id);

        return $comunicacion;
    }

    private function setId(object $object, int $id): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($object, $id);
    }
}

final class FakePresupuestoWebTelegramNotifier extends PresupuestoWebTelegramNotifier
{
    public array $notificaciones = [];
    public bool $fallar = false;

    public function __construct()
    {
    }

    public function notificar(PresupuestoWebInteraccion $interaccion): void
    {
        if ($interaccion->getTipo() === TipoPresupuestoWebInteraccion::NO_CONTINUA) {
            return;
        }

        if ($this->fallar) {
            throw new \RuntimeException('Telegram caido');
        }

        $this->notificaciones[] = $interaccion;
    }
}
