<?php

namespace App\Entity;

use App\Repository\PresupuestoWebLeadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoWebLeadRepository::class)]
class PresupuestoWebLead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $privacidadInformadaAt;

    #[ORM\Column(type: 'string', length: 32)]
    private string $versionPrivacidad;

    #[ORM\Column(type: 'boolean')]
    private bool $seguimientoActivo = true;

    #[ORM\OneToMany(mappedBy: 'lead', targetEntity: PresupuestoWeb::class)]
    private Collection $presupuestosWeb;

    public function __construct(string $email, string $versionPrivacidad, ?\DateTimeImmutable $now = null)
    {
        $now ??= new \DateTimeImmutable();
        $this->email = $email;
        $this->createdAt = $now;
        $this->privacidadInformadaAt = $now;
        $this->versionPrivacidad = $versionPrivacidad;
        $this->presupuestosWeb = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPrivacidadInformadaAt(): \DateTimeImmutable
    {
        return $this->privacidadInformadaAt;
    }

    public function getVersionPrivacidad(): string
    {
        return $this->versionPrivacidad;
    }

    public function isSeguimientoActivo(): bool
    {
        return $this->seguimientoActivo;
    }

    public function setSeguimientoActivo(bool $seguimientoActivo): self
    {
        $this->seguimientoActivo = $seguimientoActivo;

        return $this;
    }

    public function getPresupuestosWeb(): Collection
    {
        return $this->presupuestosWeb;
    }
}
