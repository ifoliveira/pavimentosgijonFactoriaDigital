<?php

namespace App\Entity;

use App\Repository\PagosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PagosRepository::class)]
class Pagos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pagos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cestas $cesta = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $fechaPg = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importePg = null;

    #[ORM\Column(type: 'string', length: 25, nullable: true)]
    private ?string $tipoPg = null;

    #[ORM\ManyToOne(inversedBy: 'pagos')]
    private ?Banco $bancoPg = null;

    #[ORM\ManyToOne(inversedBy: 'pagos', cascade: ['persist', 'remove'])]
    private ?Efectivo $efectivoPg = null;

    public function __toString()
    {
        return strval($this->id);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCesta(): ?Cestas
    {
        return $this->cesta;
    }

    public function setCesta(?Cestas $cesta): self
    {
        $this->cesta = $cesta;
        return $this;
    }

    public function getFechaPg(): ?\DateTimeInterface
    {
        return $this->fechaPg;
    }

    public function setFechaPg(?\DateTimeInterface $fechaPg): self
    {
        $this->fechaPg = $fechaPg;
        return $this;
    }

    public function getImportePg(): ?float
    {
        return $this->importePg;
    }

    public function setImportePg(?float $importePg): self
    {
        $this->importePg = $importePg;
        return $this;
    }

    public function getTipoPg(): ?string
    {
        return $this->tipoPg;
    }

    public function setTipoPg(?string $tipoPg): self
    {
        $this->tipoPg = $tipoPg;
        return $this;
    }

    public function getBancoPg(): ?Banco
    {
        return $this->bancoPg;
    }

    public function setBancoPg(?Banco $bancoPg): self
    {
        $this->bancoPg = $bancoPg;
        return $this;
    }

    public function getEfectivoPg(): ?Efectivo
    {
        return $this->efectivoPg;
    }

    public function setEfectivoPg(?Efectivo $efectivoPg): self
    {
        $this->efectivoPg = $efectivoPg;
        return $this;
    }
}