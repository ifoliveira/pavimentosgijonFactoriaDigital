<?php

namespace App\Entity;

use App\Repository\ManoObraRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ManoObraRepository::class)
 */
class ManoObra
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $tipoMo;

    /**
     * @ORM\Column(type="text")
     */
    private $textoMo;

    /**
     * @ORM\ManyToOne(targetEntity=presupuestos::class, inversedBy="manoObra")
     * @ORM\JoinColumn(nullable=false)
     */
    private $presupuestoMo;

    /**
     * @ORM\ManyToOne(targetEntity=TipoManoObra::class, inversedBy="manoObras")
     */
    private $categoriaMo;

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

    public function setTextoMo(string $textoMo): self
    {
        $this->textoMo = $textoMo;

        return $this;
    }

    public function getPresupuestoMo(): ?presupuestos
    {
        return $this->presupuestoMo;
    }

    public function setPresupuestoMo(?presupuestos $presupuestoMo): self
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
}
