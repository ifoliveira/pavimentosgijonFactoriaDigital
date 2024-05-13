<?php

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * @ORM\Entity(repositoryClass=AdminRepository::class)
 */
class Admin implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private $username;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Presupuestos", mappedBy="userPe")
     */
    private $presupuestos;

    /**
     * @ORM\OneToMany(targetEntity=Cestas::class, mappedBy="userAdmin")
     */
    private $cestas;

    public function __construct()
    {
        $this->presupuestos = new ArrayCollection();
        $this->cestas = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->username;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection|Presupuestos[]
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
            // set the owning side to null (unless already changed)
            if ($presupuesto->getUserPe() === $this) {
                $presupuesto->setUserPe(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Cestas>
     */
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
            // set the owning side to null (unless already changed)
            if ($cesta->getUserAdmin() === $this) {
                $cesta->setUserAdmin(null);
            }
        }

        return $this;
    }
}
