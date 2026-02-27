<?php

namespace App\Entity;

use App\Repository\EstadocestasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EstadocestasRepository::class)]
class Estadocestas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $descripcionEc = null;

    #[ORM\OneToMany(
        mappedBy: 'estadoPe',
        targetEntity: Presupuestos::class,
        orphanRemoval: true
    )]
    private Collection $presupuestos;

    public function __construct()
    {
        $this->presupuestos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $idEc): self
    {
        $this->id = $idEc;
        return $this;
    }

    public function getDescripcionEc(): ?string
    {
        return $this->descripcionEc;
    }

    public function setDescripcionEc(string $descripcionEc): self
    {
        $this->descripcionEc = $descripcionEc;
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->getDescripcionEc();
    }

    /**
     * @return Collection<int, Presupuestos>
     */
    public function getPresupuestos(): Collection
    {
        return $this->presupuestos;
    }

    public function addPresupuesto(Presupuestos $presupuesto): self
    {
        if (!$this->presupuestos->contains($presupuesto)) {
            $this->presupuestos[] = $presupuesto;
            $presupuesto->setEstadoPe($this);
        }

        return $this;
    }

    public function removePresupuesto(Presupuestos $presupuesto): self
    {
        if ($this->presupuestos->removeElement($presupuesto)) {
            if ($presupuesto->getEstadoPe() === $this) {
                $presupuesto->setEstadoPe(null);
            }
        }

        return $this;
    }
}