<?php

namespace App\Entity;

use App\Repository\EfectivoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EfectivoRepository::class)]
class Efectivo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'efectivo')]
    #[ORM\JoinColumn(name: 'tipoEf', referencedColumnName: 'id', nullable: true)]
    private ?Tiposmovimiento $tipoEf = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $conceptoEf = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fechaEf = null;

    #[ORM\Column(type: 'float')]
    private ?float $importeEf = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestampEf = null;

    #[ORM\ManyToOne(inversedBy: 'efectivos', cascade: ['persist', 'remove'])]
    private ?Presupuestos $presupuestoef = null;

    #[ORM\OneToMany(mappedBy: 'efectivoPg', targetEntity: Pagos::class, cascade: ['persist', 'remove'])]
    private Collection $pagos;

    public function __construct()
    {
        $this->timestampEf = new \DateTime();
        $this->pagos = new ArrayCollection();
    }

    public function __toString()
    {
        return strval($this->id);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipoEf(): ?Tiposmovimiento
    {
        return $this->tipoEf;
    }

    public function setTipoEf(?Tiposmovimiento $tipoEf): self
    {
        $this->tipoEf = $tipoEf;
        return $this;
    }

    public function getConceptoEf(): ?string
    {
        return $this->conceptoEf;
    }

    public function setConceptoEf(string $conceptoEf): self
    {
        $this->conceptoEf = $conceptoEf;
        return $this;
    }

    public function getFechaEf(): ?\DateTimeInterface
    {
        return $this->fechaEf;
    }

    public function setFechaEf(\DateTimeInterface $fechaEf): self
    {
        $this->fechaEf = $fechaEf;
        return $this;
    }

    public function getImporteEf(): ?float
    {
        return $this->importeEf;
    }

    public function setImporteEf(float $importeEf): self
    {
        $this->importeEf = $importeEf;
        return $this;
    }

    public function getTimestampEf(): ?\DateTimeInterface
    {
        return $this->timestampEf;
    }

    public function setTimestampEf(\DateTimeInterface $timestampEf): self
    {
        $this->timestampEf = $timestampEf;
        return $this;
    }

    public function getPresupuestoef(): ?Presupuestos
    {
        return $this->presupuestoef;
    }

    public function setPresupuestoef(?Presupuestos $presupuestoef): self
    {
        $this->presupuestoef = $presupuestoef;
        return $this;
    }

    /**
     * @return Collection<int, Pagos>
     */
    public function getPagos(): Collection
    {
        return $this->pagos;
    }

    public function addPago(Pagos $pago): self
    {
        if (!$this->pagos->contains($pago)) {
            $this->pagos[] = $pago;
            $pago->setEfectivoPg($this);
        }
        return $this;
    }

    public function removePago(Pagos $pago): self
    {
        if ($this->pagos->removeElement($pago)) {
            if ($pago->getEfectivoPg() === $this) {
                $pago->setEfectivoPg(null);
            }
        }
        return $this;
    }
}