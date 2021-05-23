<?php

namespace App\Entity;

use App\Repository\TipoproductoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TipoproductoRepository::class)
 */
class Tipoproducto
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $decripcion_Tp;

    /**
     * @ORM\OneToMany(targetEntity=Productos::class, mappedBy="tipo_pd_id")
     */
    private $productos;

    public function __construct()
    {
        $this->productos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDecripcionTp(): ?string
    {
        return $this->decripcion_Tp;
    }

    public function setDecripcionTp(string $decripcion_Tp): self
    {
        $this->decripcion_Tp = $decripcion_Tp;

        return $this;
    }

    /**
     * @return Collection|Productos[]
     */
    public function getProductos(): Collection
    {
        return $this->productos;
    }

    public function addProducto(Productos $producto): self
    {
        if (!$this->productos->contains($producto)) {
            $this->productos[] = $producto;
            $producto->setTipoPd($this);
        }

        return $this;
    }

    public function removeProducto(Productos $producto): self
    {
        if ($this->productos->removeElement($producto)) {
            // set the owning side to null (unless already changed)
            if ($producto->getTipoPd() === $this) {
                $producto->setTipoPd(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getDecripcionTp();
    }
}
