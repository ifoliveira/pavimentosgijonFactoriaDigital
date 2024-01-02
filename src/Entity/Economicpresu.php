<?php

namespace App\Entity;

use App\Repository\EconomicpresuRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EconomicpresuRepository::class)
 */
class Economicpresu
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $conceptoEco;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $importeEco;

    /**
     * @ORM\Column(type="string", length=1, nullable=true)
     */
    private $debehaberEco;

    /**
     * @ORM\Column(type="string", length=1, nullable=true)
     */
    private $aplicaEco;

    /**
     * @ORM\Column(type="string", length=1, nullable=true)
     */
    private $estadoEco;

    /**
     * @ORM\ManyToOne(targetEntity=presupuestos::class, inversedBy="economicpresus")
     * @ORM\JoinColumn(nullable=false)
     * @ORM\OrderBy({"estado_eco" = "ASC"})
     */
    private $idpresuEco;

    /**
     * @ORM\OneToOne(targetEntity=Banco::class, cascade={"persist", "remove"})
     */
    private $bancoEco;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $timestamp;

    /**
     * __clone
     * @return void
     */
    
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

    public function getIdpresuEco(): ?presupuestos
    {
        return $this->idpresuEco;
    }

    public function setIdpresuEco(?presupuestos $idpresuEco): self
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
