<?php

namespace App\Entity;

use App\Repository\PresupuestoConfiguradorCampoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestoConfiguradorCampoRepository::class)]
class PresupuestoConfiguradorCampo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PresupuestoConfigurador::class, inversedBy: 'campos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PresupuestoConfigurador $configurador = null;

    #[ORM\Column(length: 80)]
    private ?string $codigo = null; 
    // largo_plato, ancho_plato, tipo_mampara, producto_plato...

    #[ORM\Column(length: 150)]
    private ?string $etiqueta = null;

    #[ORM\Column(length: 50)]
    private ?string $tipoCampo = null; 
    // text, number, select, boolean, producto

    #[ORM\Column(nullable: true)]
    private ?array $opciones = null;

    #[ORM\Column]
    private bool $obligatorio = false;

    #[ORM\Column]
    private int $orden = 0;

    #[ORM\Column]
    private bool $activo = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigurador(): ?PresupuestoConfigurador
    {
        return $this->configurador;
    }

    public function setConfigurador(?PresupuestoConfigurador $configurador): static
    {
        $this->configurador = $configurador;
        return $this;
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

    public function getEtiqueta(): ?string
    {
        return $this->etiqueta;
    }

    public function setEtiqueta(string $etiqueta): static
    {
        $this->etiqueta = $etiqueta;
        return $this;
    }

    public function getTipoCampo(): ?string
    {
        return $this->tipoCampo;
    }

    public function setTipoCampo(string $tipoCampo): static
    {
        $this->tipoCampo = $tipoCampo;
        return $this;
    }

    public function getOpciones(): ?array
    {
        return $this->opciones;
    }

    public function setOpciones(?array $opciones): static
    {
        $this->opciones = $opciones;
        return $this;
    }

    public function isObligatorio(): bool
    {
        return $this->obligatorio;
    }

    public function setObligatorio(bool $obligatorio): static
    {
        $this->obligatorio = $obligatorio;
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

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;
        return $this;
    }
}