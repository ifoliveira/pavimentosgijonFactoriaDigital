<?php

namespace App\Entity;

use App\Repository\ConsultasRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConsultasRepository::class)
 */
class Consultas
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
    private $nombre;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $telefono;

    /**
     * @ORM\Column(type="string", length=2500, nullable=true)
     */
    private $pregunta;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $timestamp;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $atencion;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $presupuestoAI = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): self
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getPregunta(): ?string
    {
        return $this->pregunta;
    }

    public function setPregunta(?string $pregunta): self
    {
        $this->pregunta = $pregunta;

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

    public function isAtencion(): ?bool
    {
        return $this->atencion;
    }

    public function setAtencion(?bool $atencion): self
    {
        $this->atencion = $atencion;

        return $this;
    }

    public function getPresupuestoAI(): ?array
    {
        return $this->presupuestoAI;
    }

    public function setPresupuestoAI(?array $presupuestoAI): self
    {
        $this->presupuestoAI = $presupuestoAI;

        return $this;
    }
}
