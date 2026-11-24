<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CajaRecuento
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fecha;
    #[ORM\Column]
    private int $esperadoCentimos;
    #[ORM\Column]
    private int $contadoCentimos;
    #[ORM\Column(length: 180)]
    private string $registradoPor;

    public function __construct(int $esperado, int $contado, string $autor)
    {
        $this->fecha = new \DateTimeImmutable();
        $this->esperadoCentimos = $esperado;
        $this->contadoCentimos = $contado;
        $this->registradoPor = $autor;
    }
    public function getId(): ?int { return $this->id; }
    public function getFecha(): \DateTimeImmutable { return $this->fecha; }
    public function getEsperadoCentimos(): int { return $this->esperadoCentimos; }
    public function getContadoCentimos(): int { return $this->contadoCentimos; }
    public function getDiferenciaCentimos(): int { return $this->contadoCentimos - $this->esperadoCentimos; }
    public function getRegistradoPor(): string { return $this->registradoPor; }
}
