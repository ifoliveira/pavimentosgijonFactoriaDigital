<?php

namespace App\Entity;

use App\Repository\PagosRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Banco;

/**
 * @ORM\Entity(repositoryClass=PagosRepository::class)
 */
class Pagos
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=cestas::class, inversedBy="pagos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $cesta;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $fechaPg;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $importePg;

    /**
     * @ORM\Column(type="string", length=25, nullable=true)
     */
    private $tipoPg;

    /**
     * @ORM\ManyToOne(targetEntity=banco::class, inversedBy="pagos")
     */
    private $bancoPg;

    /**
     * @ORM\ManyToOne(targetEntity=Efectivo::class, inversedBy="pagos" , cascade={"persist","remove"})
     */
    private $efectivoPg;

    public function __toString()
    {
        return strval($this->id);
    }    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCesta(): ?cestas
    {
        return $this->cesta;
    }

    public function setCesta(?cestas $cesta): self
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

    public function getBancoPg(): ?banco
    {
        return $this->bancoPg;
    }

    public function setBancoPg(?banco $bancoPg): self
    {
        $this->bancoPg = $bancoPg;

        return $this;
    }

    public function getEfectivoPg(): ?efectivo
    {
        return $this->efectivoPg;
    }

    public function setEfectivoPg(?efectivo $efectivoPg): self
    {
        $this->efectivoPg = $efectivoPg;

        return $this;
    }
}
