<?php

namespace App\Entity;

use App\Repository\EfectivoRepository;
use App\Repository\TiposmovimientoRepository;
use Doctrine\ORM\Mapping as ORM;
use app\Entity\Tiposmovimiento;

/**
 * @ORM\Entity(repositoryClass=EfectivoRepository::class)
 */
class Efectivo
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Tiposmovimiento", inversedBy="efectivo")
     * @ORM\JoinColumn(name="tipoEf", referencedColumnName="id")
     */
    private $tipoEf;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $conceptoEf;

    /**
     * @ORM\Column(type="date")
     */
    private $fechaEf;

    /**
     * @ORM\Column(type="float")
     */
    private $importeEf;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestampEf;

    /**
     * @ORM\ManyToOne(targetEntity=presupuestos::class, inversedBy="efectivos")
     */
    private $presupuestoef;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipoEf(): ?tiposmovimiento
    {
        return $this->tipoEf;
    }

    public function setTipoEf(?tiposmovimiento $tipoEf): self
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
    
    public function __construct()
    {
      $this->timestampEf = new \DateTime();
    }

    public function getPresupuestoef(): ?presupuestos
    {
        return $this->presupuestoef;
    }

    public function setPresupuestoef(?presupuestos $presupuestoef): self
    {
        $this->presupuestoef = $presupuestoef;

        return $this;
    }
}
