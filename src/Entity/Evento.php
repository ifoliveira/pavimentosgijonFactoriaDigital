<?php

namespace App\Entity;

use App\Repository\EventoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EventoRepository::class)
 */
class Evento
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Visitante::class, inversedBy="eventos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $visitante;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $tipo;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $datos = [];

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $fechaCreacion;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitante(): ?Visitante
    {
        return $this->visitante;
    }

    public function setVisitante(?Visitante $visitante): self
    {
        $this->visitante = $visitante;

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getDatos(): ?array
    {
        return $this->datos;
    }

    public function setDatos(?array $datos): self
    {
        $this->datos = $datos;

        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeImmutable
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTimeImmutable $fechaCreacion): self
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }
}
