<?php

namespace App\Entity;

use App\Repository\EconomicpresuRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EconomicpresuRepository::class)]
class Economicpresu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $conceptoEco = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importeEco = null;

    #[ORM\Column(type: 'string', length: 1, nullable: true)]
    private ?string $debehaberEco = null;

    #[ORM\Column(type: 'string', length: 1, nullable: true)]
    private ?string $aplicaEco = null;

    #[ORM\Column(type: 'string', length: 1, nullable: true)]
    private ?string $estadoEco = null;

    #[ORM\ManyToOne(inversedBy: 'economicpresus')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Presupuestos $idpresuEco = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Banco $bancoEco = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $timestamp = null;

    public function __clone()
    {
        $this->id = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConceptoEco(): ?string
    {
        return $this->conceptoEco;
    }

    public function setConceptoEco(?string $conceptoEco): self
    {
        $this->conceptoEco = $conceptoEco;
        return $this;
    }

    public function getImporteEco(): ?float
    {
        return $this->importeEco;
    }

    public function setImporteEco(?float $importeEco): self
    {
        $this->importeEco = $importeEco;
        return $this;
    }

    public function getDebehaberEco(): ?string
    {
        return $this->debehaberEco;
    }

    public function setDebehaberEco(?string $debehaberEco): self
    {
        $this->debehaberEco = $debehaberEco;
        return $this;
    }

    public function getAplicaEco(): ?string
    {
        return $this->aplicaEco;
    }

    public function setAplicaEco(?string $aplicaEco): self
    {
        $this->aplicaEco = $aplicaEco;
        return $this;
    }

    public function getEstadoEco(): ?string
    {
        return $this->estadoEco;
    }

    public function setEstadoEco(?string $estadoEco): self
    {
        $this->estadoEco = $estadoEco;
        return $this;
    }

    public function getIdpresuEco(): ?Presupuestos
    {
        return $this->idpresuEco;
    }

    public function setIdpresuEco(?Presupuestos $idpresuEco): self
    {
        $this->idpresuEco = $idpresuEco;
        return $this;
    }

    public function getBancoEco(): ?Banco
    {
        return $this->bancoEco;
    }

    public function setBancoEco(?Banco $bancoEco): self
    {
        $this->bancoEco = $bancoEco;
        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(?\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }
}