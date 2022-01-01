<?php

namespace App\Entity;

use App\Repository\TextoManoObraRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TextoManoObraRepository::class)
 */
class TextoManoObra
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     */
    private $descripcionXo;

    /**
     * @ORM\ManyToOne(targetEntity=TipoManoObra::class, inversedBy="textoManoObras")
     * @ORM\JoinColumn(nullable=false)
     */
    private $tipoXo;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $resumenXo;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescripcionXo(): ?string
    {
        return $this->descripcionXo;
    }

    public function setDescripcionXo(string $descripcionXo): self
    {
        $this->descripcionXo = $descripcionXo;

        return $this;
    }

    public function getTipoXo(): ?TipoManoObra
    {
        return $this->tipoXo;
    }

    public function setTipoXo(?TipoManoObra $tipoXo): self
    {
        $this->tipoXo = $tipoXo;

        return $this;
    }

    public function getResumenXo(): ?string
    {
        return $this->resumenXo;
    }

    public function setResumenXo(string $resumenXo): self
    {
        $this->resumenXo = $resumenXo;

        return $this;
    }

    public function __toString()
    {
        return $this->getResumenXo();
    }
}
