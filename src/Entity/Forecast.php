<?php

namespace App\Entity;

use App\Repository\ForecastRepository;
use Doctrine\ORM\Mapping as ORM;
USE App\Entity\Banco;

/**
 * @ORM\Entity(repositoryClass=ForecastRepository::class)
 */
class Forecast
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Tiposmovimiento::class, inversedBy="forecasts")
     * @ORM\JoinColumn(nullable=false)
     */
    private $tipoFr;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $conceptoFr;

    /**
     * @ORM\Column(type="date")
     */
    private $fechaFr;

    /**
     * @ORM\Column(type="float")
     */
    private $importeFr;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $origenFr;

    /**
     * @ORM\Column(type="string", length=1)
     */
    private $fijovarFr;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $estadoFr;

    /**
     * @ORM\ManyToOne(targetEntity=banco::class)
     */
    private $banco;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $timestamp;

    public function __construct()
    {
        $this->setEstadoFr('P');
        $this->setTimestamp(new \DateTime());
        $this->setFijovarFr('V');

    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipoFr(): ?tiposmovimiento
    {
        return $this->tipoFr;
    }

    public function setTipoFr(?tiposmovimiento $tipoFr): self
    {
        $this->tipoFr = $tipoFr;

        return $this;
    }

    public function getConceptoFr(): ?string
    {
        return $this->conceptoFr;
    }

    public function setConceptoFr(string $conceptoFr): self
    {
        $this->conceptoFr = $conceptoFr;

        return $this;
    }

    public function getFechaFr(): ?\DateTimeInterface
    {
        return $this->fechaFr;
    }

    public function setFechaFr(\DateTimeInterface $fechaFr): self
    {
        $this->fechaFr = $fechaFr;

        return $this;
    }

    public function getImporteFr(): ?float
    {
        return $this->importeFr;
    }

    public function setImporteFr(float $importeFr): self
    {
        $this->importeFr = $importeFr;

        return $this;
    }

    public function getOrigenFr(): ?string
    {
        return $this->origenFr;
    }

    public function setOrigenFr(string $origenFr): self
    {
        $this->origenFr = $origenFr;

        return $this;
    }

    public function getFijovarFr(): ?string
    {
        return $this->fijovarFr;
    }

    public function setFijovarFr(string $fijovarFr): self
    {
        $this->fijovarFr = $fijovarFr;

        return $this;
    }

    public function getEstadoFr(): ?string
    {
        return $this->estadoFr;
    }

    public function setEstadoFr(string $estadoFr): self
    {
        $this->estadoFr = $estadoFr;

        return $this;
    }

    public function getBanco(): ?banco
    {
        return $this->banco;
    }

    public function setBanco(?banco $banco): self
    {
        $this->banco = $banco;

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
