<?php

namespace App\Entity;

use App\Repository\ClientesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientesRepository::class)]
class Clientes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $nombreCl = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $apellidosCl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $ciudadCl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $direccionCl = null;

    #[ORM\Column(type: 'string', length: 9, nullable: true)]
    private ?string $telefono1Cl = null;

    #[ORM\Column(type: 'string', length: 9, nullable: true)]
    private ?string $telefono2Cl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $emailCl = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $timestampaltaCl = null;

    #[ORM\OneToMany(mappedBy: 'clientePe', targetEntity: Presupuestos::class)]
    private Collection $presupuestosCl;

    #[ORM\Column(type: 'string', length: 9, nullable: true)]
    private ?string $dni = null;

    public function __construct()
    {
        $this->presupuestosCl = new ArrayCollection();
        $this->setTimestampaltaCl(new \DateTime());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombreCl(): ?string
    {
        return $this->nombreCl;
    }

    public function setNombreCl(string $nombreCl): self
    {
        $this->nombreCl = $nombreCl;
        return $this;
    }

    public function getApellidosCl(): ?string
    {
        return $this->apellidosCl;
    }

    public function setApellidosCl(?string $apellidosCl): self
    {
        $this->apellidosCl = $apellidosCl;
        return $this;
    }

    public function getCiudadCl(): ?string
    {
        return $this->ciudadCl;
    }

    public function setCiudadCl(?string $ciudadCl): self
    {
        $this->ciudadCl = $ciudadCl;
        return $this;
    }

    public function getDireccionCl(): ?string
    {
        return $this->direccionCl;
    }

    public function setDireccionCl(?string $direccionCl): self
    {
        $this->direccionCl = $direccionCl;
        return $this;
    }

    public function getTelefono1Cl(): ?string
    {
        return $this->telefono1Cl;
    }

    public function setTelefono1Cl(?string $telefono1Cl): self
    {
        $this->telefono1Cl = $telefono1Cl;
        return $this;
    }

    public function getTelefono2Cl(): ?string
    {
        return $this->telefono2Cl;
    }

    public function setTelefono2Cl(?string $telefono2Cl): self
    {
        $this->telefono2Cl = $telefono2Cl;
        return $this;
    }

    public function getEmailCl(): ?string
    {
        return $this->emailCl;
    }

    public function setEmailCl(?string $emailCl): self
    {
        $this->emailCl = $emailCl;
        return $this;
    }

    public function getTimestampaltaCl(): ?\DateTimeInterface
    {
        return $this->timestampaltaCl;
    }

    public function setTimestampaltaCl(\DateTimeInterface $timestampaltaCl): self
    {
        $this->timestampaltaCl = $timestampaltaCl;
        return $this;
    }

    public function __toString(): string
    {
        return trim(($this->getNombreCl() ?? '') . ' ' . ($this->getApellidosCl() ?? ''));
    }

    /**
     * @return Collection<int, Presupuestos>
     */
    public function getPresupuestosCl(): Collection
    {
        return $this->presupuestosCl;
    }

    public function addPresupuestosCl(Presupuestos $presupuestosCl): self
    {
        if (!$this->presupuestosCl->contains($presupuestosCl)) {
            $this->presupuestosCl[] = $presupuestosCl;
            $presupuestosCl->setClientePe($this);
        }

        return $this;
    }

    public function removePresupuestosCl(Presupuestos $presupuestosCl): self
    {
        if ($this->presupuestosCl->removeElement($presupuestosCl)) {
            if ($presupuestosCl->getClientePe() === $this) {
                $presupuestosCl->setClientePe(null);
            }
        }

        return $this;
    }

    public function getDni(): ?string
    {
        return $this->dni;
    }

    public function setDni(?string $dni): self
    {
        $this->dni = $dni;
        return $this;
    }
}