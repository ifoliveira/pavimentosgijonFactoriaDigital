<?php

namespace App\Entity;

use App\Repository\PrecioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrecioRepository::class)]
class Precio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $clave = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $valor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $grupo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tipoReforma = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClave(): ?string
    {
        return $this->clave;
    }

    public function setClave(?string $clave): static
    {
        $this->clave = $clave;

        return $this;
    }

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(?string $valor): static
    {
        $this->valor = $valor;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getGrupo(): ?string
    {
        return $this->grupo;
    }

    public function setGrupo(?string $grupo): static
    {
        $this->grupo = $grupo;

        return $this;
    }

    public function getTipoReforma(): ?string
    {
        return $this->tipoReforma;
    }

    public function setTipoReforma(?string $tipoReforma): static
    {
        $this->tipoReforma = $tipoReforma;

        return $this;
    }
}
