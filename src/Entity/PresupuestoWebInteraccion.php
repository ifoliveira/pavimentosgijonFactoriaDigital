<?php

namespace App\Entity;

use App\Enum\TipoPresupuestoWebInteraccion;
use App\Repository\PresupuestoWebInteraccionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoWebInteraccionRepository::class)]
#[ORM\Table(name: 'presupuesto_web_interaccion')]
#[ORM\Index(name: 'IDX_PRESUPUESTO_WEB_INTERACCION_TIPO', columns: ['tipo'])]
#[ORM\Index(name: 'IDX_PRESUPUESTO_WEB_INTERACCION_CREATED_AT', columns: ['created_at'])]
class PresupuestoWebInteraccion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PresupuestoWeb::class)]
    #[ORM\JoinColumn(nullable: false)]
    private PresupuestoWeb $presupuestoWeb;

    #[ORM\ManyToOne(targetEntity: PresupuestoWebComunicacion::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PresupuestoWebComunicacion $comunicacionOrigen;

    #[ORM\Column(type: 'string', length: 32)]
    private string $tipo;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $datos;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        PresupuestoWeb $presupuestoWeb,
        ?PresupuestoWebComunicacion $comunicacionOrigen,
        TipoPresupuestoWebInteraccion $tipo,
        ?array $datos = null,
        ?\DateTimeImmutable $now = null
    ) {
        $this->presupuestoWeb = $presupuestoWeb;
        $this->comunicacionOrigen = $comunicacionOrigen;
        $this->tipo = $tipo->value;
        $this->datos = $datos;
        $this->createdAt = $now ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPresupuestoWeb(): PresupuestoWeb
    {
        return $this->presupuestoWeb;
    }

    public function getComunicacionOrigen(): ?PresupuestoWebComunicacion
    {
        return $this->comunicacionOrigen;
    }

    public function getTipo(): TipoPresupuestoWebInteraccion
    {
        return TipoPresupuestoWebInteraccion::from($this->tipo);
    }

    public function getDatos(): ?array
    {
        return $this->datos;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
