<?php

namespace App\Entity;

use App\Repository\ProyectoGastoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProyectoGastoRepository::class)]
#[ORM\Table(name: 'proyecto_gasto')]
class ProyectoGasto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Proyecto al que pertenece el gasto.
     */
    #[ORM\ManyToOne(targetEntity: Proyecto::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proyecto $proyecto = null;

    /**
     * Documento relacionado opcionalmente.
     * Puede venir del presupuesto inicial, de un adicional o de la factura,
     * pero también puede ser un imprevisto sin documento asociado.
     */
    #[ORM\ManyToOne(targetEntity: Documento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Documento $documento = null;

    /**
     * Forecast asociado opcionalmente, para reflejar este gasto en tesorería.
     */
    #[ORM\ManyToOne(targetEntity: Forecast::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Forecast $forecast = null;

    /**
     * Categoría operativa del gasto.
     * Ejemplos:
     * - materiales
     * - mano_obra_externa
     * - transporte
     * - escombro
     * - subcontrata
     * - incidencia
     * - varios
     */
    #[ORM\Column(type: 'string', length: 50)]
    private ?string $categoria = null;

    /**
     * Descripción libre del gasto.
     * Ejemplo: "Pago a alicatador", "Retirada de escombro", etc.
     */
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $concepto = null;

    /**
     * Nombre del proveedor, autónomo o persona a pagar.
     */
    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $proveedor = null;

    /**
     * Fecha prevista de pago o de ocurrencia del gasto.
     */
    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fechaPrevista = null;

    /**
     * Fecha real en que se produjo o pagó el gasto.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaReal = null;

    /**
     * Importe previsto del gasto.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importePrevisto = '0.00';

    /**
     * Importe real del gasto, cuando ya se conoce.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $importeReal = null;

    /**
     * Estado del gasto:
     * - previsto
     * - confirmado
     * - pagado
     * - cancelado
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $estado = 'previsto';

    /**
     * Indica si este gasto debe reflejarse en forecast.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $generaForecast = true;

    /**
     * Observaciones internas.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $creadoEn = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    public function __construct()
    {
        $this->creadoEn = new \DateTime();
        $this->estado = 'previsto';
        $this->generaForecast = true;
        $this->importePrevisto = '0.00';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProyecto(): ?Proyecto
    {
        return $this->proyecto;
    }

    public function setProyecto(?Proyecto $proyecto): self
    {
        $this->proyecto = $proyecto;
        return $this;
    }

    public function getDocumento(): ?Documento
    {
        return $this->documento;
    }

    public function setDocumento(?Documento $documento): self
    {
        $this->documento = $documento;
        return $this;
    }

    public function getForecast(): ?Forecast
    {
        return $this->forecast;
    }

    public function setForecast(?Forecast $forecast): self
    {
        $this->forecast = $forecast;
        return $this;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function setCategoria(?string $categoria): self
    {
        $this->categoria = $categoria;
        return $this;
    }

    public function getConcepto(): ?string
    {
        return $this->concepto;
    }

    public function setConcepto(?string $concepto): self
    {
        $this->concepto = $concepto;
        return $this;
    }

    public function getProveedor(): ?string
    {
        return $this->proveedor;
    }

    public function setProveedor(?string $proveedor): self
    {
        $this->proveedor = $proveedor;
        return $this;
    }

    public function getFechaPrevista(): ?\DateTimeInterface
    {
        return $this->fechaPrevista;
    }

    public function setFechaPrevista(?\DateTimeInterface $fechaPrevista): self
    {
        $this->fechaPrevista = $fechaPrevista;
        return $this;
    }

    public function getFechaReal(): ?\DateTimeInterface
    {
        return $this->fechaReal;
    }

    public function setFechaReal(?\DateTimeInterface $fechaReal): self
    {
        $this->fechaReal = $fechaReal;
        return $this;
    }

    public function getImportePrevisto(): ?string
    {
        return $this->importePrevisto;
    }

    public function setImportePrevisto(string $importePrevisto): self
    {
        $this->importePrevisto = $importePrevisto;
        return $this;
    }

    public function getImporteReal(): ?string
    {
        return $this->importeReal;
    }

    public function setImporteReal(?string $importeReal): self
    {
        $this->importeReal = $importeReal;
        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function isGeneraForecast(): bool
    {
        return $this->generaForecast;
    }

    public function setGeneraForecast(bool $generaForecast): self
    {
        $this->generaForecast = $generaForecast;
        return $this;
    }

    public function getNotas(): ?string
    {
        return $this->notas;
    }

    public function setNotas(?string $notas): self
    {
        $this->notas = $notas;
        return $this;
    }

    public function getCreadoEn(): ?\DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function setCreadoEn(?\DateTimeInterface $creadoEn): self
    {
        $this->creadoEn = $creadoEn;
        return $this;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }

    public function setActualizadoEn(?\DateTimeInterface $actualizadoEn): self
    {
        $this->actualizadoEn = $actualizadoEn;
        return $this;
    }

    public function marcarActualizado(): self
    {
        $this->actualizadoEn = new \DateTime();
        return $this;
    }

    /**
     * Devuelve el importe más representativo para cálculos rápidos:
     * si existe real, usa real; si no, previsto.
     */
    public function getImporteEfectivo(): string
    {
        return $this->importeReal ?? $this->importePrevisto;
    }

    public function estaPagado(): bool
    {
        return $this->estado === 'pagado';
    }

    public function estaPendiente(): bool
    {
        return \in_array($this->estado, ['previsto', 'confirmado'], true);
    }
}