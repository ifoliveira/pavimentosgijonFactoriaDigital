<?php

namespace App\Tests\Service;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebLead;
use App\Enum\EstadoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Repository\PresupuestoWebRepository;
use App\Service\WebPresupuesto\PresupuestoWebService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PresupuestoWebServiceTest extends TestCase
{
    public function testCreaLeadPresupuestoYEnviaComunicacionInicial(): void
    {
        [$service, $persistidos, $emailsEnviados] = $this->service();

        $resultado = $service->crearDesdeSnapshot(' cliente@example.com ', true, $this->snapshot());

        self::assertTrue($resultado['ok']);
        self::assertCount(3, $persistidos);
        self::assertInstanceOf(PresupuestoWebLead::class, $persistidos[0]);
        self::assertInstanceOf(PresupuestoWeb::class, $persistidos[1]);
        self::assertInstanceOf(PresupuestoWebComunicacion::class, $persistidos[2]);
        self::assertSame('cliente@example.com', $persistidos[0]->getEmail());
        self::assertTrue($persistidos[0]->isSeguimientoActivo());
        self::assertSame('2026-09-19', $persistidos[0]->getVersionPrivacidad());
        self::assertSame('ducha', $persistidos[1]->getTipoPresupuesto());
        self::assertSame('397.61', $persistidos[1]->getTotal());
        self::assertSame(0, $persistidos[1]->getNumeroVisualizaciones());
        self::assertSame($this->snapshot()['jsonSolicitudBudgetFlow'], $persistidos[1]->getJsonSolicitudBudgetFlow());
        self::assertSame($this->snapshot()['jsonPresupuesto'], $persistidos[1]->getJsonPresupuesto());
        self::assertSame(TipoPresupuestoWebComunicacion::PRESUPUESTO, $persistidos[2]->getTipo());
        self::assertSame(EstadoPresupuestoWebComunicacion::ENVIADA, $persistidos[2]->getEstado());
        self::assertSame('Todos los presupuestos se parecen. Hasta que empieza la obra.', $persistidos[2]->getAsunto());
        self::assertNotNull($persistidos[2]->getFechaEnvio());
        self::assertCount(1, $emailsEnviados);
        self::assertTrue($resultado['email_enviado']);
    }

    public function testEmailInvalidoNoCreaNada(): void
    {
        [$service, $persistidos] = $this->service();

        $resultado = $service->crearDesdeSnapshot('no-es-email', true, $this->snapshot());

        self::assertFalse($resultado['ok']);
        self::assertSame('Introduce una dirección de correo electrónico válida.', $resultado['error']);
        self::assertCount(0, $persistidos);
    }

    public function testEmailVacioNoCreaNada(): void
    {
        [$service, $persistidos] = $this->service();

        $resultado = $service->crearDesdeSnapshot('   ', true, $this->snapshot());

        self::assertFalse($resultado['ok']);
        self::assertCount(0, $persistidos);
    }

    public function testPrivacidadFalseNoCreaNada(): void
    {
        [$service, $persistidos] = $this->service();

        $resultado = $service->crearDesdeSnapshot('cliente@example.com', false, $this->snapshot());

        self::assertFalse($resultado['ok']);
        self::assertSame('Debes confirmar que has leído la información sobre protección de datos.', $resultado['error']);
        self::assertCount(0, $persistidos);
    }

    public function testTokenGeneradoTieneFormatoSeguro(): void
    {
        [$service, $persistidos] = $this->service();

        $service->crearDesdeSnapshot('cliente@example.com', true, $this->snapshot());

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $persistidos[1]->getToken());
    }

    public function testTokenSeRegeneraSiExiste(): void
    {
        $repo = $this->createMock(PresupuestoWebRepository::class);
        $repo->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(new \stdClass(), null);
        [$service, $persistidos] = $this->service($repo);

        $service->crearDesdeSnapshot('cliente@example.com', true, $this->snapshot());

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $persistidos[1]->getToken());
    }

    public function testMismoEmailPuedeCrearVariosPresupuestos(): void
    {
        [$service, $persistidos] = $this->service();

        $primero = $service->crearDesdeSnapshot('cliente@example.com', true, $this->snapshot());
        $segundo = $service->crearDesdeSnapshot('cliente@example.com', true, $this->snapshot());

        self::assertTrue($primero['ok']);
        self::assertTrue($segundo['ok']);
        self::assertCount(6, $persistidos);
        self::assertNotSame($persistidos[0], $persistidos[3]);
        self::assertNotSame($persistidos[1]->getToken(), $persistidos[4]->getToken());
    }

    private function service(?PresupuestoWebRepository $repo = null): array
    {
        $persistidos = new \ArrayObject();
        $emailsEnviados = new \ArrayObject();
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn(callable $callback): mixed => $callback());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($persistidos): void {
            $persistidos->append($entity);
        });

        $repo ??= $this->createMock(PresupuestoWebRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(static function (RawMessage $message) use ($emailsEnviados): void {
            $emailsEnviados->append($message);
        });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://www.pavimentosgijon.es/presupuesto-ducha/ver/token');

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<p>Tu presupuesto esta listo</p>');

        return [
            new PresupuestoWebService($em, $repo, $mailer, $urlGenerator, $twig, '2026-09-19'),
            $persistidos,
            $emailsEnviados,
        ];
    }

    private function snapshot(): array
    {
        return [
            'tipoPresupuesto' => 'ducha',
            'total' => 397.61,
            'jsonSolicitudBudgetFlow' => [
                'configurador' => 'web_ducha',
                'valores' => [
                    'selector_plato_ducha' => [
                        'largo' => 160,
                        'ancho' => 70,
                    ],
                ],
            ],
            'jsonPresupuesto' => [
                'validacion' => ['valido' => true, 'errores' => []],
                'lineas' => [
                    ['descripcion' => 'Plato de ducha', 'importeTotal' => 397.61],
                ],
                'avisos' => [],
                'total' => 397.61,
            ],
        ];
    }
}
