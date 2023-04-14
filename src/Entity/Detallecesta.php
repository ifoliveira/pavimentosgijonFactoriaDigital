<?php

namespace App\Entity;

use App\Repository\DetallecestaRepository;
use App\Entity\Cestas;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DetallecestaRepository::class)
 */
class Detallecesta
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Cestas", inversedBy="detallecesta")
     */
    private $cestaDc;

    /**
     * @ORM\ManyToOne(targetEntity=Productos::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $productoDc;

    /**
     * @ORM\Column(type="integer")
     */
    private $cantidadDc;

    /**
     * @ORM\Column(type="float")
     */
    private $pvpDc;

    /**
     * @ORM\Column(type="float")
     */
    private $descuentoDc;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestampDc;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $precioDc;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $textoDc;

    /**
     * __clone
     * @return void
     */
    
    public function __clone()
    {
        $this->id = null;
    }        

    public function __construct()
    {
        $this->setTimestampDc(new \DateTime());
        $this->setDescuentoDc(0);

    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCestaDc(): ?cestas
    {
        return $this->cestaDc;
    }

    public function setCestaDc(?cestas $cestaDc): self
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




}
