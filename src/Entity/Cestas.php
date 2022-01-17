<?php

namespace App\Entity;

use App\Repository\CestasRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CestasRepository::class)
 */
class Cestas
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $userCs;

    /**
     * @ORM\Column(type="date")
     */
    private $fechaCs;

    /**
     * @ORM\Column(type="float")
     */
    private $importeTotCs;

    /**
     * @ORM\Column(type="float")
     */
    private $descuentoCs;

    /**
     * @ORM\Column(type="string", length=15)
     */
    private $tipopagoCs;

    /**
     * @ORM\Column(type="string", length=55)
     */
    private $numticketCs;

    /**
     * @ORM\Column(type="integer")
     */
    private $estadoCs;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestampCs;

    /**
     * @ORM\OneToMany(targetEntity=Detallecesta::class, mappedBy="cestaDc")
     */
    private $detallecesta;

    /**
     * @ORM\ManyToOne(targetEntity=presupuestos::class, inversedBy="cestas")
     */
    private $prespuestoCs;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $importePagoCs;

    public function __construct()
    {
        $this->detallecesta = new ArrayCollection();
        $this->setFechaCs(new \DateTime());
        $this->setImporteTotCs(0);
        $this->setDescuentoCs(0);
        $this->setTipopagoCs("Tarjeta");
        $this->setNumticketCs("");
        $this->setEstadoCs(1);
        $this->setTimestampCs(new \DateTime());

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserCs(): ?int
    {
        return $this->userCs;
    }

    public function setUserCs(int $userCs): self
    {
        $this->userCs = $userCs;

        return $this;
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

    public function __toString()
    {
        return strval($this->id);
    }

    /**
     * @return Collection|Detallecesta[]
     */
    public function getdetallecesta(): Collection
    {
        return $this->detallecesta;
    }


    public function adddetallecesta(Detallecesta $detallecesta): self
    {
        if (!$this->detallecesta->contains($detallecesta)) {
            $this->detallescesta[] = $detallecesta;
            $detallecesta->getCestaDc($this);
        }

        return $this;
    }

    public function removedetallescesta(Detallecesta $detallecesta): self
    {
        if ($this->detallecesta->removeElement($detallecesta)) {
            // set the owning side to null (unless already changed)
            if ($detallecesta->getCestaDc() === $this) {
                $detallecesta->setCestaDc(null);
            }
        }

        return $this;
    }

    public function getPrespuestoCs(): ?presupuestos
    {
        return $this->prespuestoCs;
    }

    public function setPrespuestoCs(?presupuestos $prespuestoCs): self
    {
        $this->prespuestoCs = $prespuestoCs;

        return $this;
    }

    public function getImportePagoCs(): ?float
    {
        return $this->importePagoCs;
    }

    public function setImportePagoCs(?float $importePagoCs): self
    {
        $this->importePagoCs = $importePagoCs;

        return $this;
    }


}
