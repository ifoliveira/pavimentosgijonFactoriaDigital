<?php

namespace App\Entity;

use App\Repository\BancoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BancoRepository::class)]
class Banco
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bancos', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'categoria_bn', referencedColumnName: 'id', nullable: true)]
    private ?Tiposmovimiento $categoria_Bn = null;

    #[ORM\Column(type: 'float')]
    private ?float $importe_Bn = null;

    #[ORM\Column(type: 'text')]
    private ?string $concepto_Bn = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fecha_Bn = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp_Bn = null;

    #[ORM\OneToMany(mappedBy: 'bancoPg', targetEntity: Pagos::class)]
    private Collection $pagos;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $conciliado = null;

    public function __construct()
    {
        $this->timestamp_Bn = new \DateTime();
        $this->pagos = new ArrayCollection();
    }

    public function __clone()
    {
        $this->id = null;
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS & SETTERS
    |--------------------------------------------------------------------------
    */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategoriaBn(): ?Tiposmovimiento
    {
        return $this->categoria_Bn;
    }

    public function setCategoriaBn(?Tiposmovimiento $categoria_Bn): self
    {
        $this->categoria_Bn = $categoria_Bn;
        return $this;
    }

    public function getImporteBn(): ?float
    {
        return $this->importe_Bn;
    }

    public function setImporteBn(float $importe_Bn): self
    {
        $this->importe_Bn = $importe_Bn;
        return $this;
    }

    public function getConceptoBn(): ?string
    {
        return $this->concepto_Bn;
    }

    public function setConceptoBn(string $concepto_Bn): self
    {
        $this->concepto_Bn = $concepto_Bn;
        return $this;
    }

    public function getFechaBn(): ?\DateTimeInterface
    {
        return $this->fecha_Bn;
    }

    public function setFechaBn(\DateTimeInterface $fecha_Bn): self
    {
        $this->fecha_Bn = $fecha_Bn;
        return $this;
    }

    public function getTimestampBn(): ?\DateTimeInterface
    {
        return $this->timestamp_Bn;
    }

    public function setTimestampBn(\DateTimeInterface $timestamp_Bn): self
    {
        $this->timestamp_Bn = $timestamp_Bn;
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
            $pago->setBancoPg($this);
        }

        return $this;
    }

    public function removePago(Pagos $pago): self
    {
        if ($this->pagos->removeElement($pago)) {
            if ($pago->getBancoPg() === $this) {
                $pago->setBancoPg(null);
            }
        }

        return $this;
    }

    public function isConciliado(): ?bool
    {
        return $this->conciliado;
    }

    public function setConciliado(?bool $conciliado): self
    {
        $this->conciliado = $conciliado;
        return $this;
    }
}