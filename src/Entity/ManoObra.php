<?php

namespace App\Entity;

use App\Repository\ManoObraRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManoObraRepository::class)]
class ManoObra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $tipoMo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textoMo = null;

    #[ORM\ManyToOne(
        targetEntity: Presupuestos::class,
        inversedBy: 'manoObra',
        fetch: 'EAGER',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: false)]
    private ?Presupuestos $presupuestoMo = null;

    #[ORM\ManyToOne(
        targetEntity: TipoManoObra::class,
        inversedBy: 'manoObras'
    )]
    private ?TipoManoObra $categoriaMo = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $coste = null;

    public function __toString(): string
    {
        return (string) $this->getPresupuestoMo()?->getClientePe()?->getDireccionCl();
    }

    public function getIdPresu(): ?int
    {
        return $this->getPresupuestoMo()?->getId();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipoMo(): ?string
    {
        return $this->tipoMo;
    }

    public function setTipoMo(string $tipoMo): self
    {
        $this->tipoMo = $tipoMo;
        return $this;
    }

    public function getTextoMo(): ?string
    {
        return $this->textoMo;
    }

    public function setTextoMo(?string $textoMo): self
    {
        $this->textoMo = $textoMo;
        return $this;
    }

    public function getPresupuestoMo(): ?Presupuestos
    {
        return $this->presupuestoMo;
    }

    public function setPresupuestoMo(?Presupuestos $presupuestoMo): self
    {
        $this->presupuestoMo = $presupuestoMo;
        return $this;
    }

    public function getCategoriaMo(): ?TipoManoObra
    {
        return $this->categoriaMo;
    }

    public function setCategoriaMo(?TipoManoObra $categoriaMo): self
    {
        $this->categoriaMo = $categoriaMo;
        return $this;
    }

    public function getCoste(): ?float
    {
        return $this->coste;
    }

    public function setCoste(?float $coste): self
    {
        $this->coste = $coste;
        return $this;
    }
}