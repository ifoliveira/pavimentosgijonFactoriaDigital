<?php

namespace App\Entity;

use App\Repository\CestasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CestasRepository::class)]
class Cestas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fechaCs = null;

    #[ORM\Column(type: 'float')]
    private ?float $importeTotCs = null;

    #[ORM\Column(type: 'float')]
    private ?float $descuentoCs = null;

    #[ORM\Column(type: 'string', length: 15)]
    private ?string $tipopagoCs = null;

    #[ORM\Column(type: 'string', length: 55)]
    private ?string $numticketCs = null;

    #[ORM\Column(type: 'integer')]
    private ?int $estadoCs = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestampCs = null;

    #[ORM\OneToMany(
        mappedBy: 'cestaDc',
        targetEntity: Detallecesta::class,
        orphanRemoval: true,
        cascade: ['persist', 'remove']
    )]
    private Collection $detallecesta;

    #[ORM\ManyToOne(inversedBy: 'cestas')]
    #[ORM\JoinColumn(name: 'prespuesto_cs_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Presupuestos $prespuestoCs = null;

    #[ORM\OneToMany(mappedBy: 'cesta', targetEntity: Pagos::class, orphanRemoval: true)]
    private Collection $pagos;

    #[ORM\ManyToOne(inversedBy: 'cestas')]
    private ?Admin $userAdmin = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaFinCs = null;

    public function __construct()
    {
        $this->detallecesta = new ArrayCollection();
        $this->pagos = new ArrayCollection();

        $this->fechaCs = new \DateTime('now', new \DateTimeZone('Europe/Madrid'));
        $this->importeTotCs = 0;
        $this->descuentoCs = 0;
        $this->tipopagoCs = "Tarjeta";
        $this->numticketCs = "";
        $this->estadoCs = 1;
        $this->timestampCs = new \DateTime('now', new \DateTimeZone('Europe/Madrid'));
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

    public function getFechaCs(): ?\DateTimeInterface
    {
        return $this->fechaCs;
    }

    public function setFechaCs(\DateTimeInterface $fechaCs): self
    {
        $this->fechaCs = $fechaCs;
        return $this;
    }

    public function getImporteTotCs(): ?float
    {
        return $this->importeTotCs;
    }

    public function setImporteTotCs(float $importeTotCs): self
    {
        $this->importeTotCs = $importeTotCs;
        return $this;
    }

    public function getDescuentoCs(): ?float
    {
        return $this->descuentoCs;
    }

    public function setDescuentoCs(float $descuentoCs): self
    {
        $this->descuentoCs = $descuentoCs;
        return $this;
    }

    public function getTipopagoCs(): ?string
    {
        return $this->tipopagoCs;
    }

    public function setTipopagoCs(string $tipopagoCs): self
    {
        $this->tipopagoCs = $tipopagoCs;
        return $this;
    }

    public function getNumticketCs(): ?string
    {
        return $this->numticketCs;
    }

    public function setNumticketCs(string $numticketCs): self
    {
        $this->numticketCs = $numticketCs;
        return $this;
    }

    public function getEstadoCs(): ?int
    {
        return $this->estadoCs;
    }

    public function setEstadoCs(?int $estadoCs): self
    {
        $this->estadoCs = $estadoCs;
        return $this;
    }

    public function getTimestampCs(): ?\DateTimeInterface
    {
        return $this->timestampCs;
    }

    public function setTimestampCs(\DateTimeInterface $timestampCs): self
    {
        $this->timestampCs = $timestampCs;
        return $this;
    }

    public function getPrespuestoCs(): ?Presupuestos
    {
        return $this->prespuestoCs;
    }

    public function setPrespuestoCs(?Presupuestos $prespuestoCs): self
    {
        $this->prespuestoCs = $prespuestoCs;
        return $this;
    }

    public function getUserAdmin(): ?Admin
    {
        return $this->userAdmin;
    }

    public function setUserAdmin(?Admin $userAdmin): self
    {
        $this->userAdmin = $userAdmin;
        return $this;
    }

    public function getFechaFinCs(): ?\DateTimeInterface
    {
        return $this->fechaFinCs;
    }

    public function setFechaFinCs(?\DateTimeInterface $fechaFinCs): self
    {
        $this->fechaFinCs = $fechaFinCs;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function getDetallecesta(): Collection
    {
        return $this->detallecesta;
    }

    public function getPagos(): Collection
    {
        return $this->pagos;
    }

    public function addPago(Pagos $pago): self
    {
        if (!$this->pagos->contains($pago)) {
            $this->pagos[] = $pago;
            $pago->setCesta($this);
        }
        return $this;
    }

    public function removePago(Pagos $pago): self
    {
        if ($this->pagos->removeElement($pago)) {
            if ($pago->getCesta() === $this) {
                $pago->setCesta(null);
            }
        }
        return $this;
    }

    public function getTotalPagos(): float
    {
        $total = 0.0;

        foreach ($this->pagos as $pago) {
            $total += $pago->getImportePg();
        }

        return $total;
    }
}