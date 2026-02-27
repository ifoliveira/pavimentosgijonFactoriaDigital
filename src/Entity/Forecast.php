<?php

namespace App\Entity;

use App\Repository\ForecastRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForecastRepository::class)]
class Forecast
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'forecasts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tiposmovimiento $tipoFr = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $conceptoFr = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fechaFr = null;

    #[ORM\Column(type: 'float')]
    private ?float $importeFr = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $origenFr = null;

    #[ORM\Column(type: 'string', length: 1)]
    private ?string $fijovarFr = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $estadoFr = null;

    #[ORM\ManyToOne]
    private ?Banco $banco = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $timestamp = null;

    public function __construct()
    {
        $this->estadoFr = 'P';
        $this->timestamp = new \DateTime();
        $this->fijovarFr = 'V';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipoFr(): ?Tiposmovimiento
    {
        return $this->tipoFr;
    }

    public function setTipoFr(?Tiposmovimiento $tipoFr): self
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

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setBanco(?Banco $banco): self
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