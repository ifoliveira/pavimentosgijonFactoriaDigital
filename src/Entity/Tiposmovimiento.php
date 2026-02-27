<?php

namespace App\Entity;

use App\Repository\TiposmovimientoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiposmovimientoRepository::class)]
class Tiposmovimiento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $descripcionTm = null;

    #[ORM\OneToMany(mappedBy: 'tipoEf', targetEntity: Efectivo::class)]
    private Collection $efectivo;

    #[ORM\OneToMany(mappedBy: 'categoria_Bn', targetEntity: Banco::class)]
    private Collection $bancos;

    #[ORM\OneToMany(mappedBy: 'tipoFr', targetEntity: Forecast::class)]
    private Collection $forecasts;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $patronBusqueda = null;

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
     * @return Collection<int, Efectivo>
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

    public function removeEfectivo(Efectivo $efectivo): self
    {
        if ($this->efectivo->removeElement($efectivo)) {
            if ($efectivo->getTipoEf() === $this) {
                $efectivo->setTipoEf(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Banco>
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
            if ($banco->getCategoriaBn() === $this) {
                $banco->setCategoriaBn(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Forecast>
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
            if ($forecast->getTipoFr() === $this) {
                $forecast->setTipoFr(null);
            }
        }

        return $this;
    }

    public function getPatronBusqueda(): ?string
    {
        return $this->patronBusqueda;
    }

    public function setPatronBusqueda(?string $patronBusqueda): self
    {
        $this->patronBusqueda = $patronBusqueda;
        return $this;
    }

    public function __toString(): string
    {
        return $this->descripcionTm ?? '';
    }
}