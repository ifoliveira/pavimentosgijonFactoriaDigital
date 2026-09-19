<?php

namespace App\Entity;

use App\Repository\PresupuestoWebAccesoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoWebAccesoRepository::class)]
#[ORM\Table(name: 'presupuesto_web_acceso')]
#[ORM\Index(name: 'IDX_PRESUPUESTO_WEB_ACCESO_FECHA', columns: ['fecha_acceso'])]
class PresupuestoWebAcceso
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PresupuestoWeb::class, inversedBy: 'accesos')]
    #[ORM\JoinColumn(nullable: false)]
    private PresupuestoWeb $presupuestoWeb;

    #[ORM\ManyToOne(targetEntity: PresupuestoWebComunicacion::class, inversedBy: 'accesos')]
    #[ORM\JoinColumn(nullable: true)]
    private ?PresupuestoWebComunicacion $comunicacionOrigen;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fechaAcceso;

    public function __construct(
        PresupuestoWeb $presupuestoWeb,
        ?PresupuestoWebComunicacion $comunicacionOrigen,
        ?\DateTimeImmutable $fechaAcceso = null
    ) {
        $this->presupuestoWeb = $presupuestoWeb;
        $this->comunicacionOrigen = $comunicacionOrigen;
        $this->fechaAcceso = $fechaAcceso ?? new \DateTimeImmutable();
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

    public function getFechaAcceso(): \DateTimeImmutable
    {
        return $this->fechaAcceso;
    }
}
