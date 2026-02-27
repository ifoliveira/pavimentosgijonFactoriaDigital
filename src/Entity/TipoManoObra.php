<?php

namespace App\Entity;

use App\Repository\TipoManoObraRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TipoManoObraRepository::class)]
class TipoManoObra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $tipoTm = null;

    #[ORM\OneToMany(
        mappedBy: 'tipoXo',
        targetEntity: TextoManoObra::class,
        orphanRemoval: true
    )]
    private Collection $textoManoObras;

    #[ORM\OneToMany(
        mappedBy: 'categoriaMo',
        targetEntity: ManoObra::class
    )]
    private Collection $manoObras;

    public function __construct()
    {
        $this->textoManoObras = new ArrayCollection();
        $this->manoObras = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipoTm(): ?string
    {
        return $this->tipoTm;
    }

    public function setTipoTm(string $tipoTm): self
    {
        $this->tipoTm = $tipoTm;
        return $this;
    }

    /**
     * @return Collection<int, TextoManoObra>
     */
    public function getTextoManoObras(): Collection
    {
        return $this->textoManoObras;
    }

    public function addTextoManoObra(TextoManoObra $textoManoObra): self
    {
        if (!$this->textoManoObras->contains($textoManoObra)) {
            $this->textoManoObras[] = $textoManoObra;
            $textoManoObra->setTipoXo($this);
        }

        return $this;
    }

    public function removeTextoManoObra(TextoManoObra $textoManoObra): self
    {
        if ($this->textoManoObras->removeElement($textoManoObra)) {
            if ($textoManoObra->getTipoXo() === $this) {
                $textoManoObra->setTipoXo(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ManoObra>
     */
    public function getManoObras(): Collection
    {
        return $this->manoObras;
    }

    public function addManoObra(ManoObra $manoObra): self
    {
        if (!$this->manoObras->contains($manoObra)) {
            $this->manoObras[] = $manoObra;
            $manoObra->setCategoriaMo($this);
        }

        return $this;
    }

    public function removeManoObra(ManoObra $manoObra): self
    {
        if ($this->manoObras->removeElement($manoObra)) {
            if ($manoObra->getCategoriaMo() === $this) {
                $manoObra->setCategoriaMo(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->tipoTm ?? '';
    }
}