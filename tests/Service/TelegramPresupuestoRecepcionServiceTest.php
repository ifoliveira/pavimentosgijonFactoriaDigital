<?php

namespace App\Tests\Service;

use App\Dto\FichaVisita\FichaVisitaDatos;
use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\TelegramPresupuestoRecepcion;
use App\Repository\TelegramPresupuestoRecepcionRepository;
use App\Service\FichaVisita\FichaVisitaAiExtractorInterface;
use App\Service\FichaVisita\FichaVisitaStorage;
use App\Service\FichaVisita\PresupuestoDesdeFotoService;
use App\Service\Telegram\TelegramAutorizacion;
use App\Service\Telegram\TelegramBotClient;
use App\Service\Telegram\TelegramPresupuestoRecepcionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class TelegramPresupuestoRecepcionServiceTest extends TestCase
{
    public function testFotoAutorizadaGeneraYNotifica(): void
    {
        [$service, $telegram, $flow] = $this->service();

        $service->procesarUpdate($this->photoUpdate());

        self::assertSame(1, $flow->ejecuciones);
        self::assertStringContainsString('Presupuesto preparado', implode("\n", $telegram->sent));
    }

    public function testDocumentoImagenSeAcepta(): void
    {
        [$service, , $flow] = $this->service();

        $service->procesarUpdate($this->documentUpdate());

        self::assertSame(1, $flow->ejecuciones);
    }

    public function testFormatoRechazadoNoEjecutaFlujo(): void
    {
        [$service, $telegram, $flow] = $this->service(fileContents: 'not-image');

        $service->procesarUpdate($this->documentUpdate());

        self::assertSame(0, $flow->ejecuciones);
        self::assertStringContainsString('Formato no admitido', implode("\n", $telegram->sent));
    }

    public function testTamanoRechazadoNoDescarga(): void
    {
        [$service, $telegram, $flow] = $this->service(fileSize: 11 * 1024 * 1024);

        $service->procesarUpdate($this->photoUpdate());

        self::assertSame(0, $flow->ejecuciones);
        self::assertStringContainsString('10 MB', implode("\n", $telegram->sent));
    }

    public function testAlbumRechazado(): void
    {
        [$service, $telegram, $flow] = $this->service();
        $update = $this->photoUpdate();
        $update['message']['media_group_id'] = 'album-1';

        $service->procesarUpdate($update);

        self::assertSame(0, $flow->ejecuciones);
        self::assertStringContainsString('álbum', implode("\n", $telegram->sent));
    }

    public function testActualizacionRepetidaNoRegenera(): void
    {
        [$service, , $flow, $repo] = $this->service();

        $service->procesarUpdate($this->photoUpdate());
        $service->procesarUpdate($this->photoUpdate());

        self::assertCount(1, $repo->items);
        self::assertSame(1, $flow->ejecuciones);
    }

    public function testDatosPendientesAntesDeCrearDocumentoEnviaEnlace(): void
    {
        [$service, $telegram, $flow] = $this->service(resultado: ['status' => PresupuestoDesdeFotoService::PENDIENTE_CLIENTE, 'datos' => ['pendientes' => ['telefono']]]);

        $service->procesarUpdate($this->photoUpdate());

        self::assertSame(1, $flow->ejecuciones);
        self::assertStringContainsString('/admin/proyecto/desde-foto/telegram/', implode("\n", $telegram->sent));
    }

    public function testRevisionTecnicaDespuesDeCrearDocumentoEnviaRevision(): void
    {
        [$service, $telegram] = $this->service(resultado: ['status' => PresupuestoDesdeFotoService::REVISION_TECNICA, 'datos' => [], 'documento' => $this->documento(), 'proyecto' => new Proyecto()]);

        $service->procesarUpdate($this->photoUpdate());

        self::assertStringContainsString('/admin/proyecto/desde-foto/revisar/', implode("\n", $telegram->sent));
    }

    public function testFalloBudgetFlowEnviaRevisionSinRecrear(): void
    {
        [$service, $telegram, $flow] = $this->service(resultado: ['status' => PresupuestoDesdeFotoService::BUDGETFLOW_ERROR, 'datos' => [], 'documento' => $this->documento(), 'proyecto' => new Proyecto(), 'warning' => 'BudgetFlow caído']);

        $service->procesarUpdate($this->photoUpdate());

        self::assertSame(1, $flow->ejecuciones);
        self::assertStringContainsString('/admin/proyecto/desde-foto/revisar/', implode("\n", $telegram->sent));
    }

    public function testFalloNotificacionNoRepiteGeneracion(): void
    {
        [$service, $telegram, $flow] = $this->service();
        $telegram->failOnSendNumber = 2;

        $service->procesarUpdate($this->photoUpdate());
        $telegram->failOnSendNumber = null;
        $service->procesarUpdate($this->photoUpdate());

        self::assertSame(1, $flow->ejecuciones);
        self::assertStringContainsString('Presupuesto preparado', implode("\n", $telegram->sent));
    }

    private function service(?array $resultado = null, string $fileContents = null, int $fileSize = 1000): array
    {
        $telegram = new FakeTelegramBotClient($fileContents ?? $this->pngBytes(), $fileSize);
        $repo = new FakeTelegramRecepcionRepository();
        $storage = new FichaVisitaStorage(new AsciiSlugger(), sys_get_temp_dir().'/telegram-test-'.bin2hex(random_bytes(4)));
        $extractor = new FakeFichaExtractor();
        $flow = new FakePresupuestoDesdeFotoService($resultado ?? ['status' => PresupuestoDesdeFotoService::COMPLETADO, 'datos' => [], 'documento' => $this->documento(), 'proyecto' => new Proyecto()]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(fn (object $entity) => $repo->persist($entity));
        $em->method('flush')->willReturn(null);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generate')->willReturnCallback(fn (string $route, array $params) => match ($route) {
            'app_documento_show' => '/admin/documento/'.$params['id'],
            'app_proyecto_desde_foto_revisar' => '/admin/proyecto/desde-foto/revisar/'.$params['id'],
            default => '/admin/proyecto/desde-foto/telegram/'.$params['id'].'/completar',
        });

        return [
            new TelegramPresupuestoRecepcionService($telegram, new TelegramAutorizacion('123'), $repo, $storage, $extractor, $flow, $em, $url, 'https://erp.test'),
            $telegram,
            $flow,
            $repo,
        ];
    }

    private function photoUpdate(): array
    {
        return ['update_id' => 10, 'message' => ['message_id' => 20, 'chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'photo' => [['file_id' => 'photo', 'file_size' => 1000]]]];
    }

    private function documentUpdate(): array
    {
        return ['update_id' => 11, 'message' => ['message_id' => 21, 'chat' => ['id' => 123, 'type' => 'private'], 'from' => ['id' => 123], 'document' => ['file_id' => 'doc', 'file_name' => 'ficha.png', 'file_size' => 1000]]];
    }

    private function documento(): Documento
    {
        $cliente = new Clientes();
        $cliente->setNombreCl('Ana');
        $proyecto = new Proyecto();
        $proyecto->setNombre('Cambio de bañera por ducha');
        $documento = new Documento();
        $documento->setSerie('P2026');
        $documento->setNumero(18);
        $documento->setTotal('1234.56');
        $documento->setCliente($cliente);
        $documento->setProyecto($proyecto);

        return $documento;
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}

final class FakeTelegramBotClient extends TelegramBotClient
{
    public array $sent = [];
    public ?int $failOnSendNumber = null;
    private int $sendCount = 0;

    public function __construct(private readonly string $contents, private readonly int $fileSize)
    {
    }

    public function botId(): string { return 'bot'; }
    public function assertConfigured(): void {}
    public function getFile(string $fileId): array { return ['file_path' => 'file', 'file_size' => $this->fileSize]; }
    public function downloadFile(string $filePath): string { return $this->contents; }
    public function sendMessage(string|int $chatId, string $text): void
    {
        $this->sendCount++;
        if ($this->failOnSendNumber === $this->sendCount) {
            throw new \RuntimeException('Telegram caído');
        }
        $this->sent[] = $text;
    }
}

final class FakeTelegramRecepcionRepository extends TelegramPresupuestoRecepcionRepository
{
    /** @var array<int, TelegramPresupuestoRecepcion> */
    public array $items = [];

    public function __construct()
    {
    }

    public function findOneByBotAndUpdate(string $botId, int $updateId): ?TelegramPresupuestoRecepcion
    {
        return $this->items[$updateId] ?? null;
    }

    public function persist(object $entity): void
    {
        if ($entity instanceof TelegramPresupuestoRecepcion) {
            $this->items[$entity->getUpdateId()] = $entity;
        }
    }
}

final class FakeFichaExtractor implements FichaVisitaAiExtractorInterface
{
    public function extraer(string $absoluteImagePath, string $mimeType): FichaVisitaDatos
    {
        return FichaVisitaDatos::fromArray(['nombre_cliente' => 'Ana', 'telefono' => '600111222', 'direccion_obra' => 'Calle', 'pendientes' => []]);
    }
}

final class FakePresupuestoDesdeFotoService extends PresupuestoDesdeFotoService
{
    public int $ejecuciones = 0;

    public function __construct(private readonly array $resultado)
    {
    }

    public function ejecutar(array $datos, string $obra, string $descripcion, ?string $direccionObra, ?string $fichaFotoPath, ?int $clienteSeleccionadoId = null): array
    {
        $this->ejecuciones++;

        return $this->resultado;
    }
}
