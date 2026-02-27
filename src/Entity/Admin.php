<?php

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
class Admin implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[ORM\OneToMany(mappedBy: 'userPe', targetEntity: Presupuestos::class)]
    private Collection $presupuestos;

    #[ORM\OneToMany(mappedBy: 'userAdmin', targetEntity: Cestas::class)]
    private Collection $cestas;

    public function __construct()
    {
        $this->presupuestos = new ArrayCollection();
        $this->cestas = new ArrayCollection();
    }

    public function __toString()
    {
        return (string) $this->username;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function getPresupuestos(): Collection
    {
        return $this->presupuestos;
    }

    public function addPresupuesto(Presupuestos $presupuesto): self
    {
        if (!$this->presupuestos->contains($presupuesto)) {
            $this->presupuestos[] = $presupuesto;
            $presupuesto->setUserPe($this);
        }

        return $this;
    }

    public function removePresupuesto(Presupuestos $presupuesto): self
    {
        if ($this->presupuestos->removeElement($presupuesto)) {
            if ($presupuesto->getUserPe() === $this) {
                $presupuesto->setUserPe(null);
            }
        }

        return $this;
    }

    public function getCestas(): Collection
    {
        return $this->cestas;
    }

    public function addCesta(Cestas $cesta): self
    {
        if (!$this->cestas->contains($cesta)) {
            $this->cestas[] = $cesta;
            $cesta->setUserAdmin($this);
        }

        return $this;
    }

    public function removeCesta(Cestas $cesta): self
    {
        if ($this->cestas->removeElement($cesta)) {
            if ($cesta->getUserAdmin() === $this) {
                $cesta->setUserAdmin(null);
            }
        }

        return $this;
    }
}