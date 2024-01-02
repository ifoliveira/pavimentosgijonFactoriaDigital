<?php

namespace App\Entity;

use App\Repository\BancoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use app\Entity\Tiposmovimiento;
use App\Repository\TiposmovimientoRepository;


/**
 * @ORM\Entity(repositoryClass=BancoRepository::class)
 */
class Banco
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Tiposmovimiento", inversedBy="bancos",cascade={"persist"})
     * @ORM\JoinColumn(name="categoria_bn", referencedColumnName="id")
     */
    private $categoria_Bn;

    /**
     * @ORM\Column(type="float")
     */
    private $importe_Bn;

    /**
     * @ORM\Column(type="text")
     */
    private $concepto_Bn;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha_Bn;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestamp_Bn;

    /**
     * @ORM\OneToMany(targetEntity=Pagos::class, mappedBy="bancoPg")
     */
    private $pagos;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $conciliado;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }


    public function getCategoriaBn(): ?tiposmovimiento
    {
        return $this->categoria_Bn;
    }

    public function setCategoriaBn(?tiposmovimiento $categoria_Bn): self
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

    public function __construct()
    {
      $this->timestamp_Bn = new \DateTime();
      $this->pagos = new ArrayCollection();
    }

    /**
     * __clone
     * @return void
     */
    
    public function __clone()
    {
        $this->id = null;
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
            // set the owning side to null (unless already changed)
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
