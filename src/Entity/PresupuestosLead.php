<?php

namespace App\Entity;

use App\Repository\PresupuestosLeadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestosLeadRepository::class)]
class PresupuestosLead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $tipoReforma = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $fechaPdf = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $email1Enviado = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $email2Enviado = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $seguimientoActivo = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pdfDescargas = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $ultimoEvento = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $jsonPresupuesto = [];

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $total = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $manoObra = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $materiales = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getTipoReforma(): ?string
    {
        return $this->tipoReforma;
    }

    public function setTipoReforma(?string $tipoReforma): self
    {
        $this->tipoReforma = $tipoReforma;
        return $this;
    }

    public function getFechaPdf(): ?\DateTimeInterface
    {
        return $this->fechaPdf;
    }

    public function setFechaPdf(\DateTimeInterface $fechaPdf): self
    {
        $this->fechaPdf = $fechaPdf;
        return $this;
    }

    public function isEmail1Enviado(): ?bool
    {
        return $this->email1Enviado;
    }

    public function setEmail1Enviado(?bool $email1Enviado): self
    {
        $this->email1Enviado = $email1Enviado;
        return $this;
    }

    public function isEmail2Enviado(): ?bool
    {
        return $this->email2Enviado;
    }

    public function setEmail2Enviado(?bool $email2Enviado): self
    {
        $this->email2Enviado = $email2Enviado;
        return $this;
    }

    public function isSeguimientoActivo(): ?bool
    {
        return $this->seguimientoActivo;
    }

    public function setSeguimientoActivo(?bool $seguimientoActivo): self
    {
        $this->seguimientoActivo = $seguimientoActivo;
        return $this;
    }

    public function getPdfDescargas(): ?int
    {
        return $this->pdfDescargas;
    }

    public function setPdfDescargas(?int $pdfDescargas): self
    {
        $this->pdfDescargas = $pdfDescargas;
        return $this;
    }

    public function getUltimoEvento(): ?\DateTimeInterface
    {
        return $this->ultimoEvento;
    }

    public function setUltimoEvento(?\DateTimeInterface $ultimoEvento): self
    {
        $this->ultimoEvento = $ultimoEvento;
        return $this;
    }

    public function getJsonPresupuesto(): ?array
    {
        return $this->jsonPresupuesto;
    }

    public function setJsonPresupuesto(?array $jsonPresupuesto): self
    {
        $this->jsonPresupuesto = $jsonPresupuesto;
        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(?float $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getManoObra(): ?float
    {
        return $this->manoObra;
    }

    public function setManoObra(?float $manoObra): self
    {
        $this->manoObra = $manoObra;
        return $this;
    }

    public function getMateriales(): ?float
    {
        return $this->materiales;
    }

    public function setMateriales(?float $materiales): self
    {
        $this->materiales = $materiales;
        return $this;
    }
}