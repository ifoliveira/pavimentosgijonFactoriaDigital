<?php

namespace App\Entity;

use App\Repository\SerieDocumentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SerieDocumentoRepository::class)]
#[ORM\Table(name: 'serie_documento')]
#[ORM\UniqueConstraint(name: 'uniq_serie_documento_codigo', columns: ['codigo'])]
class SerieDocumento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private string $codigo;

    #[ORM\Column(type: 'integer')]
    private int $ultimoNumero = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $creadoEn;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    public function __construct()
    {
        $this->creadoEn = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function getUltimoNumero(): int
    {
        return $this->ultimoNumero;
    }

    public function setUltimoNumero(int $ultimoNumero): static
    {
        $this->ultimoNumero = $ultimoNumero;
        return $this;
    }

    public function incrementar(): int
    {
        $this->ultimoNumero++;
        $this->actualizadoEn = new \DateTime();

        return $this->ultimoNumero;
    }

    public function getCreadoEn(): \DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }
}