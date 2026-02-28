<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SesionRepository;

#[ORM\Entity(repositoryClass: SesionRepository::class)]
class Sesion
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Visitante::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Visitante $visitante = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $fechaInicio = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaFin = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rutaEntrada = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $dispositivo = null;

    #[ORM\Column(type: 'integer')]
    private int $numeroEventos = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaUltimoEvento = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isBot = null;

    #[ORM\Column(nullable: true)]
    private ?bool $jsConfirmed = null;

    /*
    |--------------------------------------------------------------------------
    | GETTERS & SETTERS
    |--------------------------------------------------------------------------
    */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
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

    public function getFechaInicio(): ?\DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function setFechaInicio(\DateTimeImmutable $fechaInicio): self
    {
        $this->fechaInicio = $fechaInicio;
        return $this;
    }

    public function getFechaFin(): ?\DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function setFechaFin(?\DateTimeImmutable $fechaFin): self
    {
        $this->fechaFin = $fechaFin;
        return $this;
    }

    public function getRutaEntrada(): ?string
    {
        return $this->rutaEntrada;
    }

    public function setRutaEntrada(?string $rutaEntrada): self
    {
        $this->rutaEntrada = $rutaEntrada;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getDispositivo(): ?string
    {
        return $this->dispositivo;
    }

    public function setDispositivo(?string $dispositivo): self
    {
        $this->dispositivo = $dispositivo;
        return $this;
    }

    public function getNumeroEventos(): int
    {
        return $this->numeroEventos;
    }

    public function setNumeroEventos(int $numeroEventos): self
    {
        $this->numeroEventos = $numeroEventos;
        return $this;
    }

    public function incrementarEventos(): self
    {
        $this->numeroEventos++;
        return $this;
    }

    public function getFechaUltimoEvento(): ?\DateTimeInterface
    {
        return $this->fechaUltimoEvento;
    }

    public function setFechaUltimoEvento(?\DateTimeInterface $fechaUltimoEvento): static
    {
        $this->fechaUltimoEvento = $fechaUltimoEvento;

        return $this;
    }

    public function isIsBot(): ?bool
    {
        return $this->isBot;
    }

    public function setIsBot(?bool $isBot): static
    {
        $this->isBot = $isBot;

        return $this;
    }

    public function isJsConfirmed(): ?bool
    {
        return $this->jsConfirmed;
    }

    public function setJsConfirmed(?bool $jsConfirmed): static
    {
        $this->jsConfirmed = $jsConfirmed;

        return $this;
    }
}