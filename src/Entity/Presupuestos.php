<?php

namespace App\Entity;

use App\Repository\PresupuestosRepository;
use App\Entity\estadocestas;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PresupuestosRepository::class)
 */
class Presupuestos
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Estadocestas", inversedBy="presupuestos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $estadoPe;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Admin", inversedBy="presupuestos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $userPe;

    /**
     * @ORM\Column(type="date")
     */
    private $fechainiPe;

    /**
     * @ORM\Column(type="float")
     */
    private $costetotPe;

    /**
     * @ORM\Column(type="float")
     */
    private $importetotPe;

    /**
     * @ORM\Column(type="float")
     */
    private $descuaetoPe;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $tipopagototPE;

    /**
     * @ORM\Column(type="float")
     */
    private $importesnalPe;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $tipopagosnalPe;
   
     /**
     * @ORM\Column(type="string", length=20)
     */
    private $ticketsnal;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Cestas", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $ticket;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $manoobraPe;

    /**
     * @ORM\ManyToOne(targetEntity=Clientes::class, inversedBy="presupuestosCl")
     * @ORM\JoinColumn(nullable=false)
     */
    private $clientePe;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestampModPe;

    /**
     * @ORM\OneToMany(targetEntity=ManoObra::class, mappedBy="presupuestoMo", cascade={"persist"}, orphanRemoval=true)
     * @ORM\OrderBy({"presupuestoMo" = "ASC","categoriaMo" = "ASC"})
     */
    private $manoObra;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $importemanoobra;

    /**
     * @ORM\OneToMany(targetEntity=Cestas::class, mappedBy="prespuestoCs")
     */
    private $cestas;

    public function __construct()
    {

        $this->setFechainiPe(new \DateTime());
        $this->setTimestampModPe(new \DateTime());

        $this->setCostetotPe(0);
        $this->setDescuaetoPe(0);
        $this->setImportetotPe(0);
        $this->manoObra = new ArrayCollection();
        $this->cestas = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstadoPe(): ?estadocestas
    {
        return $this->estadoPe;
    }

    public function setEstadoPe(?estadocestas $estadoPe): self
    {
        $this->estadoPe = $estadoPe;

        return $this;
    }

    public function getUserPe(): ?admin
    {
        return $this->userPe;
    }

    public function setUserPe(?admin $userPe): self
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

    public function getTicket(): ?cestas
    {
        return $this->ticket;
    }

    public function setTicket(cestas $ticket): self
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
        return $this->getClientePe()->getDireccionCl();
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
            // set the owning side to null (unless already changed)
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
            // set the owning side to null (unless already changed)
            if ($cesta->getPrespuestoCs() === $this) {
                $cesta->setPrespuestoCs(null);
            }
        }

        return $this;
    }
}
