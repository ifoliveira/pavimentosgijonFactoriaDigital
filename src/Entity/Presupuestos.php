<?php

namespace App\Entity;

use App\Repository\PresupuestosRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresupuestosRepository::class)]
class Presupuestos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'presupuestos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Estadocestas $estadoPe = null;

    #[ORM\ManyToOne(inversedBy: 'presupuestos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Admin $userPe = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechainiPe = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $costetotPe = null;

    #[ORM\Column(type: 'float')]
    private ?float $importetotPe = null;

    #[ORM\Column(type: 'float')]
    private ?float $descuaetoPe = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $tipopagototPE = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importesnalPe = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $tipopagosnalPe = null;

    #[ORM\OneToOne(orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Cestas $ticket = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $manoobraPe = null;

    #[ORM\ManyToOne(inversedBy: 'presupuestosCl', fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'cliente_pe_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Clientes $clientePe = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $timestampModPe = null;

    #[ORM\OneToMany(mappedBy: 'presupuestoMo', targetEntity: ManoObra::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['presupuestoMo' => 'ASC', 'categoriaMo' => 'ASC'])]
    private Collection $manoObra;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importemanoobra = null;

    #[ORM\OneToMany(mappedBy: 'prespuestoCs', targetEntity: Cestas::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cestas;

    #[ORM\OneToMany(mappedBy: 'idpresuEco', targetEntity: Economicpresu::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['estadoEco' => 'ASC'])]
    private Collection $economicpresus;

    #[ORM\OneToMany(mappedBy: 'presupuestoef', targetEntity: Efectivo::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $efectivos;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $impmanoobraPagado = null;

    #[ORM\OneToMany(mappedBy: 'presupuesto', targetEntity: UsoDeStock::class)]
    private Collection $usoDeStocks;

    public function __construct()
    {
        $this->setFechainiPe(new \DateTime());
        $this->setTimestampModPe(new \DateTime('now', new \DateTimeZone('Europe/Madrid')));

        $this->setCostetotPe(0);
        $this->setDescuaetoPe(0);
        $this->setImportetotPe(0);

        $this->manoObra = new ArrayCollection();
        $this->cestas = new ArrayCollection();
        $this->economicpresus = new ArrayCollection();
        $this->efectivos = new ArrayCollection();
        $this->usoDeStocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstadoPe(): ?Estadocestas
    {
        return $this->estadoPe;
    }

    public function setEstadoPe(?Estadocestas $estadoPe): self
    {
        $this->estadoPe = $estadoPe;
        return $this;
    }

    public function getUserPe(): ?Admin
    {
        return $this->userPe;
    }

    public function setUserPe(?Admin $userPe): self
    {
        $this->userPe = $userPe;
        return $this;
    }

    public function getFechainiPe(): ?\DateTimeInterface
    {
        return $this->fechainiPe;
    }

    public function setFechainiPe(\DateTimeInterface $fechainiPe): self
    {
        $this->fechainiPe = $fechainiPe;
        return $this;
    }

    public function getCostetotPe(): ?float
    {
        return $this->costetotPe;
    }

    public function setCostetotPe(float $costetotPe): self
    {
        $this->costetotPe = $costetotPe;
        return $this;
    }

    public function getImportetotPe(): ?float
    {
        return $this->importetotPe;
    }

    public function setImportetotPe(float $importetotPe): self
    {
        $this->importetotPe = $importetotPe;
        return $this;
    }

    public function getDescuaetoPe(): ?float
    {
        return $this->descuaetoPe;
    }

    public function setDescuaetoPe(float $descuaetoPe): self
    {
        $this->descuaetoPe = $descuaetoPe;
        return $this;
    }

    public function getTipopagototPE(): ?string
    {
        return $this->tipopagototPE;
    }

    public function setTipopagototPE(string $tipopagototPE): self
    {
        $this->tipopagototPE = $tipopagototPE;
        return $this;
    }

    public function getImportesnalPe(): ?float
    {
        return $this->importesnalPe;
    }

    public function setImportesnalPe(float $importesnalPe): self
    {
        $this->importesnalPe = $importesnalPe;
        return $this;
    }

    public function getTipopagosnalPe(): ?string
    {
        return $this->tipopagosnalPe;
    }

    public function setTipopagosnalPe(string $tipopagosnalPe): self
    {
        $this->tipopagosnalPe = $tipopagosnalPe;
        return $this;
    }

    public function getTicket(): ?Cestas
    {
        return $this->ticket;
    }

    public function setTicket(Cestas $ticket): self
    {
        $this->ticket = $ticket;
        return $this;
    }

    public function getManoobraPe(): ?string
    {
        return $this->manoobraPe;
    }

    public function setManoobraPe(?string $manoobraPe): self
    {
        $this->manoobraPe = $manoobraPe;
        return $this;
    }

    public function getClientePe(): ?Clientes
    {
        return $this->clientePe;
    }

    public function setClientePe(?Clientes $clientePe): self
    {
        $this->clientePe = $clientePe;
        return $this;
    }

    public function getTimestampModPe(): ?\DateTimeInterface
    {
        return $this->timestampModPe;
    }

    public function setTimestampModPe(\DateTimeInterface $timestampModPe): self
    {
        $this->timestampModPe = $timestampModPe;
        return $this;
    }

    public function __toString()
    {
        return $this->getClientePe()?->getDireccionCl() ?? (string) $this->id;
    }

    /**
     * @return Collection|ManoObra[]
     */
    public function getManoObra(): Collection
    {
        return $this->manoObra;
    }

    public function addManoObra(ManoObra $manoObra): self
    {
        if (!$this->manoObra->contains($manoObra)) {
            $this->manoObra[] = $manoObra;
            $manoObra->setPresupuestoMo($this);
        }
        return $this;
    }

    public function removeManoObra(ManoObra $manoObra): self
    {
        if ($this->manoObra->removeElement($manoObra)) {
            if ($manoObra->getPresupuestoMo() === $this) {
                $manoObra->setPresupuestoMo(null);
            }
        }
        return $this;
    }

    public function getImportemanoobra(): ?float
    {
        return $this->importemanoobra;
    }

    public function setImportemanoobra(?float $importemanoobra): self
    {
        $this->importemanoobra = $importemanoobra;
        return $this;
    }

    /**
     * @return Collection|Cestas[]
     */
    public function getCestas(): Collection
    {
        return $this->cestas;
    }

    public function addCesta(Cestas $cesta): self
    {
        if (!$this->cestas->contains($cesta)) {
            $this->cestas[] = $cesta;
            $cesta->setPrespuestoCs($this);
        }
        return $this;
    }

    public function removeCesta(Cestas $cesta): self
    {
        if ($this->cestas->removeElement($cesta)) {
            if ($cesta->getPrespuestoCs() === $this) {
                $cesta->setPrespuestoCs(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Economicpresu>
     */
    public function getEconomicpresus(): Collection
    {
        return $this->economicpresus;
    }

    public function addEconomicpresu(Economicpresu $economicpresu): self
    {
        if (!$this->economicpresus->contains($economicpresu)) {
            $this->economicpresus[] = $economicpresu;
            $economicpresu->setIdpresuEco($this);
        }
        return $this;
    }

    public function removeEconomicpresu(Economicpresu $economicpresu): self
    {
        if ($this->economicpresus->removeElement($economicpresu)) {
            if ($economicpresu->getIdpresuEco() === $this) {
                $economicpresu->setIdpresuEco(null);
            }
        }
        return $this;
    }

    public function getEconomicPagosPdtes(): array
    {
        return array_filter($this->economicpresus->toArray(), function ($eco) {
            return $eco->getAplicaEco() == 'E' && $eco->getEstadoEco() == 1;
        });
    }

    /**
     * @return Collection<int, Efectivo>
     */
    public function getEfectivos(): Collection
    {
        return $this->efectivos;
    }

    public function addEfectivo(Efectivo $efectivo): self
    {
        if (!$this->efectivos->contains($efectivo)) {
            $this->efectivos[] = $efectivo;
            $efectivo->setPresupuestoef($this);
        }
        return $this;
    }

    public function removeEfectivo(Efectivo $efectivo): self
    {
        if ($this->efectivos->removeElement($efectivo)) {
            if ($efectivo->getPresupuestoef() === $this) {
                $efectivo->setPresupuestoef(null);
            }
        }
        return $this;
    }

    public function getImpmanoobraPagado(): ?float
    {
        return $this->impmanoobraPagado;
    }

    public function setImpmanoobraPagado(?float $impmanoobraPagado): self
    {
        $this->impmanoobraPagado = $impmanoobraPagado;
        return $this;
    }

    /**
     * @return Collection<int, UsoDeStock>
     */
    public function getUsoDeStocks(): Collection
    {
        return $this->usoDeStocks;
    }

    public function addUsoDeStock(UsoDeStock $usoDeStock): self
    {
        if (!$this->usoDeStocks->contains($usoDeStock)) {
            $this->usoDeStocks[] = $usoDeStock;
            $usoDeStock->setPresupuesto($this);
        }
        return $this;
    }

    public function removeUsoDeStock(UsoDeStock $usoDeStock): self
    {
        if ($this->usoDeStocks->removeElement($usoDeStock)) {
            if ($usoDeStock->getPresupuesto() === $this) {
                $usoDeStock->setPresupuesto(null);
            }
        }
        return $this;
    }
}