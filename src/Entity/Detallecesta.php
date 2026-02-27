<?php

namespace App\Entity;

use App\Repository\DetallecestaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DetallecestaRepository::class)]
class Detallecesta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'detallecesta')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Cestas $cestaDc = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Productos $productoDc = null;

    #[ORM\Column(type: 'integer')]
    private ?int $cantidadDc = null;

    #[ORM\Column(type: 'float')]
    private ?float $pvpDc = null;

    #[ORM\Column(type: 'float')]
    private ?float $descuentoDc = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $timestampDc = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $precioDc = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $textoDc = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $costeActualizadoPorFactura = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $facturaOrigen = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $fechaActualizacionCoste = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $costeAnterior = null;

    public function __construct()
    {
        $this->setTimestampDc(new \DateTime());
        $this->setDescuentoDc(0);
    }

    public function __clone()
    {
        $this->id = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCestaDc(): ?Cestas
    {
        return $this->cestaDc;
    }

    public function setCestaDc(?Cestas $cestaDc): self
    {
        $this->cestaDc = $cestaDc;
        return $this;
    }

    public function getProductoDc(): ?Productos
    {
        return $this->productoDc;
    }

    public function setProductoDc(?Productos $productoDc): self
    {
        $this->productoDc = $productoDc;
        return $this;
    }

    public function getCantidadDc(): ?int
    {
        return $this->cantidadDc;
    }

    public function setCantidadDc(int $cantidadDc): self
    {
        $this->cantidadDc = $cantidadDc;
        return $this;
    }

    public function getPvpDc(): ?float
    {
        return $this->pvpDc;
    }

    public function setPvpDc(float $pvpDc): self
    {
        $this->pvpDc = $pvpDc;
        return $this;
    }

    public function getDescuentoDc(): ?float
    {
        return $this->descuentoDc;
    }

    public function setDescuentoDc(float $descuentoDc): self
    {
        $this->descuentoDc = $descuentoDc;
        return $this;
    }

    public function getTimestampDc(): ?\DateTimeInterface
    {
        return $this->timestampDc;
    }

    public function setTimestampDc(\DateTimeInterface $timestampDc): self
    {
        $this->timestampDc = $timestampDc;
        return $this;
    }

    public function getPrecioDc(): ?float
    {
        return $this->precioDc;
    }

    public function setPrecioDc(?float $precioDc): self
    {
        $this->precioDc = $precioDc;
        return $this;
    }

    public function getTextoDc(): ?string
    {
        return $this->textoDc;
    }

    public function setTextoDc(?string $textoDc): self
    {
        $this->textoDc = $textoDc;
        return $this;
    }

    public function isCosteActualizadoPorFactura(): ?bool
    {
        return $this->costeActualizadoPorFactura;
    }

    public function setCosteActualizadoPorFactura(?bool $costeActualizadoPorFactura): self
    {
        $this->costeActualizadoPorFactura = $costeActualizadoPorFactura;
        return $this;
    }

    public function getFacturaOrigen(): ?string
    {
        return $this->facturaOrigen;
    }

    public function setFacturaOrigen(?string $facturaOrigen): self
    {
        $this->facturaOrigen = $facturaOrigen;
        return $this;
    }

    public function getFechaActualizacionCoste(): ?\DateTimeInterface
    {
        return $this->fechaActualizacionCoste;
    }

    public function setFechaActualizacionCoste(?\DateTimeInterface $fechaActualizacionCoste): self
    {
        $this->fechaActualizacionCoste = $fechaActualizacionCoste;
        return $this;
    }

    public function getCosteAnterior(): ?float
    {
        return $this->costeAnterior;
    }

    public function setCosteAnterior(?float $costeAnterior): self
    {
        $this->costeAnterior = $costeAnterior;
        return $this;
    }
}