<?php

namespace App\Entity;

use App\Repository\PresupuestoConfiguradorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoConfiguradorRepository::class)]
class PresupuestoConfigurador
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $codigo = null; // ducha, bano_completo

    #[ORM\Column(length: 150)]
    private ?string $nombre = null;

    #[ORM\Column]
    private bool $activo = true;

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\OneToMany(mappedBy: 'configurador', targetEntity: PresupuestoConfiguradorCampo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orden' => 'ASC'])]
    private Collection $campos;

    public function __construct()
    {
        $this->campos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;
        return $this;
    }

    public function getOrden(): int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;
        return $this;
    }

    public function getCampos(): Collection
    {
        return $this->campos;
    }

    public function addCampo(PresupuestoConfiguradorCampo $campo): static
    {
        if (!$this->campos->contains($campo)) {
            $this->campos->add($campo);
            $campo->setConfigurador($this);
        }

        return $this;
    }

    public function removeCampo(PresupuestoConfiguradorCampo $campo): static
    {
        if ($this->campos->removeElement($campo)) {
            if ($campo->getConfigurador() === $this) {
                $campo->setConfigurador(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}