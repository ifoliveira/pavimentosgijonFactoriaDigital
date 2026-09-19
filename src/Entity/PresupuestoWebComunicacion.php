<?php

namespace App\Entity;

use App\Enum\EstadoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Repository\PresupuestoWebComunicacionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoWebComunicacionRepository::class)]
#[ORM\Table(name: 'presupuesto_web_comunicacion')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRESUPUESTO_WEB_COMUNICACION_TOKEN_ACCESO', columns: ['token_acceso'])]
#[ORM\Index(name: 'IDX_PRESUPUESTO_WEB_COMUNICACION_ESTADO', columns: ['estado'])]
#[ORM\Index(name: 'IDX_PRESUPUESTO_WEB_COMUNICACION_FECHA_PROGRAMADA', columns: ['fecha_programada'])]
class PresupuestoWebComunicacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PresupuestoWeb::class, inversedBy: 'comunicaciones')]
    #[ORM\JoinColumn(nullable: false)]
    private PresupuestoWeb $presupuestoWeb;

    #[ORM\Column(type: 'string', length: 32)]
    private string $tipo;

    #[ORM\Column(type: 'string', length: 32)]
    private string $estado;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaProgramada = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaEnvio = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $asunto = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $tokenAcceso;

    #[ORM\OneToMany(mappedBy: 'comunicacionOrigen', targetEntity: PresupuestoWebAcceso::class)]
    private Collection $accesos;

    public function __construct(
        PresupuestoWeb $presupuestoWeb,
        TipoPresupuestoWebComunicacion $tipo,
        EstadoPresupuestoWebComunicacion $estado,
        ?\DateTimeImmutable $now = null
    ) {
        $this->presupuestoWeb = $presupuestoWeb;
        $this->tipo = $tipo->value;
        $this->estado = $estado->value;
        $this->createdAt = $now ?? new \DateTimeImmutable();
        $this->tokenAcceso = bin2hex(random_bytes(32));
        $this->accesos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPresupuestoWeb(): PresupuestoWeb
    {
        return $this->presupuestoWeb;
    }

    public function getTipo(): TipoPresupuestoWebComunicacion
    {
        return TipoPresupuestoWebComunicacion::from($this->tipo);
    }

    public function getEstado(): EstadoPresupuestoWebComunicacion
    {
        return EstadoPresupuestoWebComunicacion::from($this->estado);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFechaProgramada(): ?\DateTimeImmutable
    {
        return $this->fechaProgramada;
    }

    public function getFechaEnvio(): ?\DateTimeImmutable
    {
        return $this->fechaEnvio;
    }

    public function getAsunto(): ?string
    {
        return $this->asunto;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getTokenAcceso(): string
    {
        return $this->tokenAcceso;
    }

    public function getAccesos(): Collection
    {
        return $this->accesos;
    }

    public function marcarEnviada(string $asunto, \DateTimeImmutable $fechaEnvio): self
    {
        $this->asunto = $asunto;
        $this->estado = EstadoPresupuestoWebComunicacion::ENVIADA->value;
        $this->fechaEnvio = $fechaEnvio;
        $this->error = null;

        return $this;
    }

    public function marcarError(string $asunto, string $error): self
    {
        $this->asunto = $asunto;
        $this->estado = EstadoPresupuestoWebComunicacion::ERROR->value;
        $this->error = $error;

        return $this;
    }

    public function marcarCancelada(): self
    {
        $this->estado = EstadoPresupuestoWebComunicacion::CANCELADA->value;

        return $this;
    }
}
