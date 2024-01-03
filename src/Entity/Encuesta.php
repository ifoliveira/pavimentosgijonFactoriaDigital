<?php

namespace App\Entity;

use App\Repository\EncuestaRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\Uuid;

use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EncuestaRepository::class)
 */
class Encuesta
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\GeneratedValue(strategy="CUSTOM")
     */
    private $id;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $fecha;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $cliente;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p1;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p2;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p3;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p4;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $p5;

    /**
     * @ORM\Column(type="array", length=10, nullable=true)
     */
    private $p6;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p7;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p8;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p9;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $p10;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $P11 = [];

    public function __construct() {
        $uuid = Uuid::v4();
                
        $this->id = $uuid;
    }


    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(?\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getCliente(): ?string
    {
        return $this->cliente;
    }

    public function setCliente(?string $cliente): self
    {
        $this->cliente = $cliente;

        return $this;
    }

    public function getP1(): ?string
    {
        return $this->p1;
    }

    public function setP1(?string $p1): self
    {
        $this->p1 = $p1;

        return $this;
    }

    public function getP2(): ?string
    {
        return $this->p2;
    }

    public function setP2(?string $p2): self
    {
        $this->p2 = $p2;

        return $this;
    }

    public function getP3(): ?string
    {
        return $this->p3;
    }

    public function setP3(?string $p3): self
    {
        $this->p3 = $p3;

        return $this;
    }

    public function getP4(): ?string
    {
        return $this->p4;
    }

    public function setP4(?string $p4): self
    {
        $this->p4 = $p4;

        return $this;
    }

    public function getP5(): ?array
    {
        return $this->p5;
    }

    public function setP5(?array $p5): self
    {
        $this->p5 = $p5;

        return $this;
    }

    public function getP6(): ?array
    {
        return $this->p6;
    }

    public function setP6(?array $p6): self
    {
        $this->p6 = $p6;

        return $this;
    }

    public function getP7(): ?string
    {
        return $this->p7;
    }

    public function setP7(?string $p7): self
    {
        $this->p7 = $p7;

        return $this;
    }

    public function getP8(): ?string
    {
        return $this->p8;
    }

    public function setP8(?string $p8): self
    {
        $this->p8 = $p8;

        return $this;
    }

    public function getP9(): ?string
    {
        return $this->p9;
    }

    public function setP9(?string $p9): self
    {
        $this->p9 = $p9;

        return $this;
    }

    public function getP10(): ?string
    {
        return $this->p10;
    }

    public function setP10(?string $p10): self
    {
        $this->p10 = $p10;

        return $this;
    }

    public function getP11(): ?array
    {
        return $this->P11;
    }

    public function setP11(?array $P11): self
    {
        $this->P11 = $P11;

        return $this;
    }
}
