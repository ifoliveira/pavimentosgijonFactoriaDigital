<?php

namespace App\Entity;

use App\Repository\PresupuestosLeadRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PresupuestosLeadRepository::class)
 */
class PresupuestosLead
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nombre;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $tipoReforma;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fechaPdf;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $email1Enviado;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $email2Enviado;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $seguimientoActivo;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pdfDescargas;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $ultimoEvento;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $jsonPresupuesto = [];

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $total;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $manoObra;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $materiales;

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
