<?php

namespace App\Entity;

use App\Repository\DocumentoConfiguracionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentoConfiguracionRepository::class)]
class DocumentoConfiguracion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Documento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Documento $documento = null;

    #[ORM\ManyToOne(targetEntity: PresupuestoConfigurador::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PresupuestoConfigurador $configurador = null;

    #[ORM\Column]
    private array $datos = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 50)]
    private ?string $codigoConfigurador = null;

    public function getCodigoConfigurador(): ?string
    {
        return $this->codigoConfigurador;
    }

    public function setCodigoConfigurador(string $codigoConfigurador): static
    {
        $this->codigoConfigurador = $codigoConfigurador;

        return $this;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumento(): ?Documento
    {
        return $this->documento;
    }

    public function setDocumento(Documento $documento): static
    {
        $this->documento = $documento;
        return $this;
    }

    public function getConfigurador(): ?PresupuestoConfigurador
    {
        return $this->configurador;
    }

    public function setConfigurador(PresupuestoConfigurador $configurador): static
    {
        $this->configurador = $configurador;
        return $this;
    }

    public function getDatos(): array
    {
        return $this->datos;
    }

    public function setDatos(array $datos): static
    {
        $this->datos = $datos;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getDato(string $clave, mixed $default = null): mixed
    {
        return $this->datos[$clave] ?? $default;
    }

    public function setDato(string $clave, mixed $valor): static
    {
        $this->datos[$clave] = $valor;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}