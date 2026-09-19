<?php

namespace App\Tests\Service;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebAcceso;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebLead;
use App\Enum\EstadoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Repository\PresupuestoWebComunicacionRepository;
use App\Service\WebPresupuesto\PresupuestoWebAccesoService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class PresupuestoWebAccesoServiceTest extends TestCase
{
    public function testGetSoloGeneraNonceYNoContabiliza(): void
    {
        [$service, $persistidos] = $this->service();
        $session = $this->session();
        $presupuesto = $this->presupuesto(25);

        $nonce = $service->generarViewNonce($session, $presupuesto, null);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $nonce);
        self::assertSame(0, $presupuesto->getNumeroVisualizaciones());
        self::assertCount(0, $persistidos);
    }

    public function testPostValidoCreaAccesoYActualizaResumen(): void
    {
        [$service, $persistidos] = $this->service();
        $session = $this->session();
        $presupuesto = $this->presupuesto(25);
        $nonce = $service->generarViewNonce($session, $presupuesto, null);

        $resultado = $service->registrarVisualizacion($session, $presupuesto, $nonce);

        self::assertTrue($resultado['ok']);
        self::assertTrue($resultado['counted']);
        self::assertCount(1, $persistidos);
        self::assertInstanceOf(PresupuestoWebAcceso::class, $persistidos[0]);
        self::assertSame($presupuesto, $persistidos[0]->getPresupuestoWeb());
        self::assertNull($persistidos[0]->getComunicacionOrigen());
        self::assertSame(1, $presupuesto->getNumeroVisualizaciones());
        self::assertNotNull($presupuesto->getPrimeraVisualizacionAt());
        self::assertNotNull($presupuesto->getUltimaVisualizacionAt());
    }

    public function testNonceDeUnSoloUso(): void
    {
        [$service, $persistidos] = $this->service();
        $session = $this->session();
        $presupuesto = $this->presupuesto(25);
        $nonce = $service->generarViewNonce($session, $presupuesto, null);

        $primero = $service->registrarVisualizacion($session, $presupuesto, $nonce);
        $segundo = $service->registrarVisualizacion($session, $presupuesto, $nonce);

        self::assertTrue($primero['counted']);
        self::assertFalse($segundo['ok']);
        self::assertFalse($segundo['counted']);
        self::assertCount(1, $persistidos);
    }

    public function testNonceInvalidoNoCuenta(): void
    {
        [$service, $persistidos] = $this->service();
        $resultado = $service->registrarVisualizacion($this->session(), $this->presupuesto(25), 'nope');

        self::assertFalse($resultado['ok']);
        self::assertFalse($resultado['counted']);
        self::assertCount(0, $persistidos);
    }

    public function testNonceDeOtroPresupuestoNoCuenta(): void
    {
        [$service, $persistidos] = $this->service();
        $session = $this->session();
        $presupuesto = $this->presupuesto(25);
        $otroPresupuesto = $this->presupuesto(26);
        $nonce = $service->generarViewNonce($session, $otroPresupuesto, null);

        $resultado = $service->registrarVisualizacion($session, $presupuesto, $nonce);

        self::assertFalse($resultado['ok']);
        self::assertFalse($resultado['counted']);
        self::assertCount(0, $persistidos);
    }

    public function testComunicacionValidaSeAtribuye(): void
    {
        $presupuesto = $this->presupuesto(25);
        $comunicacion = $this->comunicacion(7, $presupuesto);
        [$service, $persistidos] = $this->service([
            'token-a' => $comunicacion,
            7 => $comunicacion,
        ]);
        $session = $this->session();

        $resuelta = $service->resolverComunicacionOrigen($presupuesto, 'token-a');
        $nonce = $service->generarViewNonce($session, $presupuesto, $resuelta);
        $resultado = $service->registrarVisualizacion($session, $presupuesto, $nonce);

        self::assertSame($comunicacion, $resuelta);
        self::assertTrue($resultado['counted']);
        self::assertSame($comunicacion, $persistidos[0]->getComunicacionOrigen());
    }

    public function testComunicacionDeOtroPresupuestoNoSeAtribuye(): void
    {
        $presupuesto = $this->presupuesto(25);
        $otroPresupuesto = $this->presupuesto(26);
        $comunicacionAjena = $this->comunicacion(7, $otroPresupuesto);
        [$service, $persistidos] = $this->service(['token-ajeno' => $comunicacionAjena]);
        $session = $this->session();

        $resuelta = $service->resolverComunicacionOrigen($presupuesto, 'token-ajeno');
        $nonce = $service->generarViewNonce($session, $presupuesto, $resuelta);
        $resultado = $service->registrarVisualizacion($session, $presupuesto, $nonce);

        self::assertNull($resuelta);
        self::assertTrue($resultado['counted']);
        self::assertNull($persistidos[0]->getComunicacionOrigen());
    }

    public function testDedupeMismaComunicacionEnMismaSesion(): void
    {
        $presupuesto = $this->presupuesto(25);
        $comunicacion = $this->comunicacion(7, $presupuesto);
        [$service, $persistidos] = $this->service([7 => $comunicacion]);
        $session = $this->session();

        $nonceA = $service->generarViewNonce($session, $presupuesto, $comunicacion);
        $nonceB = $service->generarViewNonce($session, $presupuesto, $comunicacion);

        $primero = $service->registrarVisualizacion($session, $presupuesto, $nonceA);
        $segundo = $service->registrarVisualizacion($session, $presupuesto, $nonceB);

        self::assertTrue($primero['counted']);
        self::assertTrue($segundo['ok']);
        self::assertFalse($segundo['counted']);
        self::assertSame('dedupe', $segundo['reason']);
        self::assertCount(1, $persistidos);
    }

    public function testComunicacionDiferenteCuentaAunqueEsteDentroDeDedupe(): void
    {
        $presupuesto = $this->presupuesto(25);
        $comunicacionA = $this->comunicacion(7, $presupuesto);
        $comunicacionB = $this->comunicacion(8, $presupuesto);
        [$service, $persistidos] = $this->service([
            7 => $comunicacionA,
            8 => $comunicacionB,
        ]);
        $session = $this->session();

        $service->registrarVisualizacion($session, $presupuesto, $service->generarViewNonce($session, $presupuesto, $comunicacionA));
        $resultadoB = $service->registrarVisualizacion($session, $presupuesto, $service->generarViewNonce($session, $presupuesto, $comunicacionB));

        self::assertTrue($resultadoB['counted']);
        self::assertCount(2, $persistidos);
        self::assertSame(2, $presupuesto->getNumeroVisualizaciones());
    }

    public function testMismaComunicacionFueraDeVentanaDedupeVuelveAContar(): void
    {
        $presupuesto = $this->presupuesto(25);
        $comunicacion = $this->comunicacion(7, $presupuesto);
        [$service, $persistidos] = $this->service([7 => $comunicacion], 1800);
        $session = $this->session();

        $service->registrarVisualizacion($session, $presupuesto, $service->generarViewNonce($session, $presupuesto, $comunicacion));
        $session->set('presupuesto_web_view_dedupe', ['25:7' => time() - 1900]);
        $resultado = $service->registrarVisualizacion($session, $presupuesto, $service->generarViewNonce($session, $presupuesto, $comunicacion));

        self::assertTrue($resultado['counted']);
        self::assertCount(2, $persistidos);
        self::assertSame(2, $presupuesto->getNumeroVisualizaciones());
        self::assertLessThanOrEqual($presupuesto->getUltimaVisualizacionAt(), $presupuesto->getPrimeraVisualizacionAt());
    }

    private function service(array $comunicaciones = [], int $dedupeSeconds = 1800): array
    {
        $persistidos = new \ArrayObject();
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
        $repo->method('find')->willReturnCallback(static function (mixed $id) use ($comunicaciones): ?PresupuestoWebComunicacion {
            return $comunicaciones[$id] ?? null;
        });

        return [
            new PresupuestoWebAccesoService($em, $repo, $dedupeSeconds),
            $persistidos,
        ];
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function presupuesto(int $id): PresupuestoWeb
    {
        $lead = new PresupuestoWebLead('cliente@example.com', '2026-09-19');
        $presupuesto = new PresupuestoWeb($lead, 'ducha', bin2hex(random_bytes(32)), ['configurador' => 'web_ducha'], ['lineas' => []], '100.00');
        $this->setId($presupuesto, $id);

        return $presupuesto;
    }

    private function comunicacion(int $id, PresupuestoWeb $presupuesto): PresupuestoWebComunicacion
    {
        $comunicacion = new PresupuestoWebComunicacion(
            $presupuesto,
            TipoPresupuestoWebComunicacion::PRESUPUESTO,
            EstadoPresupuestoWebComunicacion::ENVIADA
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
