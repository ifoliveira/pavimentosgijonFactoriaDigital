<?php

namespace App\Entity;

use App\Repository\TelegramPresupuestoRecepcionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TelegramPresupuestoRecepcionRepository::class)]
#[ORM\Table(name: 'telegram_presupuesto_recepcion')]
#[ORM\UniqueConstraint(name: 'uniq_telegram_presupuesto_bot_update', columns: ['bot_id', 'update_id'])]
class TelegramPresupuestoRecepcion
{
    public const ESTADO_RECIBIDA = 'recibida';
    public const ESTADO_PENDIENTE_CLIENTE = 'pendiente_cliente';
    public const ESTADO_REVISION_TECNICA = 'revision_tecnica';
    public const ESTADO_COMPLETADA = 'completada';
    public const ESTADO_ERROR = 'error';
    public const ESTADO_NOTIFICACION_PENDIENTE = 'notificacion_pendiente';
    public const ESTADO_RECHAZADA = 'rechazada';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $botId;

    #[ORM\Column]
    private int $updateId;

    #[ORM\Column(type: 'bigint')]
    private string $chatId;

    #[ORM\Column]
    private int $messageId;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $fromUserId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $fromUsername = null;

    #[ORM\Column(length: 40)]
    private string $estado = self::ESTADO_RECIBIDA;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagenPath = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $datosExtraidos = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $adaptacion = null;

    #[ORM\ManyToOne(targetEntity: Proyecto::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Proyecto $proyecto = null;

    #[ORM\ManyToOne(targetEntity: Documento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Documento $documento = null;

    #[ORM\Column(nullable: true)]
    private ?int $ultimoUpdateProcesado = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $actualizadoEn;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notificadoEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $errorResumen = null;

    public function __construct(string $botId, int $updateId, string|int $chatId, int $messageId)
    {
        $this->botId = $botId;
        $this->updateId = $updateId;
        $this->chatId = (string) $chatId;
        $this->messageId = $messageId;
        $this->creadoEn = new \DateTimeImmutable();
        $this->actualizadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getBotId(): string { return $this->botId; }
    public function getUpdateId(): int { return $this->updateId; }
    public function getChatId(): string { return $this->chatId; }
    public function getMessageId(): int { return $this->messageId; }
    public function getFromUserId(): ?string { return $this->fromUserId; }
    public function getEstado(): string { return $this->estado; }
    public function getImagenPath(): ?string { return $this->imagenPath; }
    public function getDatosExtraidos(): ?array { return $this->datosExtraidos; }
    public function getAdaptacion(): ?array { return $this->adaptacion; }
    public function getProyecto(): ?Proyecto { return $this->proyecto; }
    public function getDocumento(): ?Documento { return $this->documento; }
    public function getUltimoUpdateProcesado(): ?int { return $this->ultimoUpdateProcesado; }
    public function getErrorResumen(): ?string { return $this->errorResumen; }
    public function getNotificadoEn(): ?\DateTimeImmutable { return $this->notificadoEn; }

    public function setFromUserId(string|int|null $fromUserId): self
    {
        $this->fromUserId = $fromUserId !== null ? (string) $fromUserId : null;
        return $this->touch();
    }

    public function setFromUsername(?string $fromUsername): self
    {
        $this->fromUsername = $fromUsername !== null ? mb_substr($fromUsername, 0, 120) : null;
        return $this->touch();
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        return $this->touch();
    }

    public function setImagenPath(?string $imagenPath): self
    {
        $this->imagenPath = $imagenPath;
        return $this->touch();
    }

    public function setDatosExtraidos(?array $datosExtraidos): self
    {
        $this->datosExtraidos = $datosExtraidos;
        return $this->touch();
    }

    public function setAdaptacion(?array $adaptacion): self
    {
        $this->adaptacion = $adaptacion;
        return $this->touch();
    }

    public function setProyecto(?Proyecto $proyecto): self
    {
        $this->proyecto = $proyecto;
        return $this->touch();
    }

    public function setDocumento(?Documento $documento): self
    {
        $this->documento = $documento;
        return $this->touch();
    }

    public function setUltimoUpdateProcesado(?int $ultimoUpdateProcesado): self
    {
        $this->ultimoUpdateProcesado = $ultimoUpdateProcesado;
        return $this->touch();
    }

    public function setErrorResumen(?string $errorResumen): self
    {
        $this->errorResumen = $errorResumen !== null ? mb_substr($errorResumen, 0, 255) : null;
        return $this->touch();
    }

    public function marcarNotificada(): self
    {
        $this->notificadoEn = new \DateTimeImmutable();
        return $this->touch();
    }

    private function touch(): self
    {
        $this->actualizadoEn = new \DateTimeImmutable();
        return $this;
    }
}
