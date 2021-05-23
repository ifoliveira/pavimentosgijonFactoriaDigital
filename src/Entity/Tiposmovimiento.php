<?php

namespace App\Entity;

use App\Repository\TiposmovimientoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TiposmovimientoRepository::class)
 */
class Tiposmovimiento
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
    private $descripcionTm;

    /**
     * @ORM\OneToMany(targetEntity=Efectivo::class, mappedBy="tipoEf")
     */
    private $efectivo;

    /**
     * @ORM\OneToMany(targetEntity=Banco::class, mappedBy="categoria_Bn")
     */
    private $bancos;

    /**
     * @ORM\OneToMany(targetEntity=Forecast::class, mappedBy="tipoFr")
     */
    private $forecasts;

    public function __construct()
    {
        $this->efectivo = new ArrayCollection();
        $this->bancos = new ArrayCollection();
        $this->forecasts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescripcionTm(): ?string
    {
        return $this->descripcionTm;
    }

    public function setDescripcionTm(string $descripcionTm): self
    {
        $this->descripcionTm = $descripcionTm;

        return $this;
    }

    /**
     * @return Collection|Efectivo[]
     */
    public function getEfectivo(): Collection
    {
        return $this->efectivo;
    }

    public function addEfectivo(Efectivo $efectivo): self
    {
        if (!$this->efectivo->contains($efectivo)) {
            $this->efectivo[] = $efectivo;
            $efectivo->setTipoEf($this);
        }

        return $this;
    }

    public function removeProducto(Efectivo $efectivo): self
    {
        if ($this->efectivo->removeElement($efectivo)) {
            // set the owning side to null (unless already changed)
            if ($efectivo->getTipoEf() === $this) {
                $efectivo->setTipoEf(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getDescripcionTm();
    }

    /**
     * @return Collection|Banco[]
     */
    public function getBancos(): Collection
    {
        return $this->bancos;
    }

    public function addBanco(Banco $banco): self
    {
        if (!$this->bancos->contains($banco)) {
            $this->bancos[] = $banco;
            $banco->setCategoriaBn($this);
        }

        return $this;
    }

    public function removeBanco(Banco $banco): self
    {
        if ($this->bancos->removeElement($banco)) {
            // set the owning side to null (unless already changed)
            if ($banco->getCategoriaBn() === $this) {
                $banco->setCategoriaBn(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Forecast[]
     */
    public function getForecasts(): Collection
    {
        return $this->forecasts;
    }

    public function addForecast(Forecast $forecast): self
    {
        if (!$this->forecasts->contains($forecast)) {
            $this->forecasts[] = $forecast;
            $forecast->setTipoFr($this);
        }

        return $this;
    }

    public function removeForecast(Forecast $forecast): self
    {
        if ($this->forecasts->removeElement($forecast)) {
            // set the owning side to null (unless already changed)
            if ($forecast->getTipoFr() === $this) {
                $forecast->setTipoFr(null);
            }
        }

        return $this;
    }

}
