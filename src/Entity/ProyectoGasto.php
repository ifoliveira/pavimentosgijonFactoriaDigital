<?php

namespace App\Entity;

use App\Repository\ProyectoGastoRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Banco;
use App\Entity\Efectivo;

#[ORM\Entity(repositoryClass: ProyectoGastoRepository::class)]
#[ORM\Table(name: 'proyecto_gasto', indexes: [
    new ORM\Index(name: 'idx_estado', columns: ['estado']),
    new ORM\Index(name: 'idx_fecha_prevista', columns: ['fecha_prevista']),
    new ORM\Index(name: 'idx_categoria', columns: ['categoria']),
])]
class ProyectoGasto
{

    public const ESTADO_PREVISTO = 'previsto';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_PAGADO = 'pagado';
    public const ESTADO_CANCELADO = 'cancelado';
    public const ORIGEN_MANUAL = 'manual';
    public const ORIGEN_FACTURA_PROVEEDOR = 'factura_proveedor';


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Proyecto al que pertenece el gasto.
     */
    #[ORM\ManyToOne(
        targetEntity: Proyecto::class,
        inversedBy: 'gastos'
    )]
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

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $fechaConfirmado = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $fechaPagado = null;    

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $origen = null;

    #[ORM\Column(type: 'boolean')]
    private bool $afectaCaja = true;

    /**
     * Estado del gasto:
     * - previsto
     * - confirmado
     * - pagado
     * - cancelado
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $estado = self::ESTADO_PREVISTO;

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

    /**
     * Importe TOTAL previsto del gasto.
     * Es el dinero que realmente saldrá de banco/caja.
     * Incluye IVA y, si procede, recargo de equivalencia.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importePrevisto = '0.00';

    /**
     * Base imponible prevista.
     * Es la referencia principal para calcular el coste económico del proyecto
     * cuando el IVA es deducible.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $basePrevista = null;

    /**
     * Tipo de IVA previsto: 0, 4, 10, 21...
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $tipoIvaPrevisto = null;

    /**
     * Importe de IVA previsto.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $ivaPrevisto = null;

    /**
     * Recargo de equivalencia previsto, si existe.
     * Es importe, no porcentaje.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $recargoPrevisto = null;


    /**
     * Importe TOTAL real del gasto.
     * Es el importe finalmente pagado / facturado.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $importeReal = null;

    /**
     * Base imponible real.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $baseReal = null;

    /**
     * Tipo de IVA real.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $tipoIvaReal = null;

    /**
     * Importe de IVA real.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $ivaReal = null;

    /**
     * Recargo de equivalencia real, si existe.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $recargoReal = null;

    /**
     * Indica si el IVA de este gasto es fiscalmente deducible.
     *
     * Normalmente true para facturas de proveedor ordinarias.
     * Permite que determinados gastos incorporen el IVA como coste real.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $ivaDeducible = true;

    #[ORM\ManyToOne(targetEntity: Banco::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Banco $bancoMovimiento = null;

    #[ORM\ManyToOne(targetEntity: Efectivo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Efectivo $efectivoMovimiento = null;


    public function __construct()
    {
        $this->creadoEn = new \DateTime();
        $this->generaForecast = true;
        $this->importePrevisto = '0.00';
        $this->estado = self::ESTADO_PREVISTO;
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
        return $this->estado === self::ESTADO_PAGADO;
    }

    public function estaPendiente(): bool
    {
        return \in_array($this->estado, [
            self::ESTADO_PREVISTO,
            self::ESTADO_CONFIRMADO,
        ], true);
    }
    public function getBancoMovimiento(): ?Banco
    {
        return $this->bancoMovimiento;
    }

    public function setBancoMovimiento(?Banco $bancoMovimiento): self
    {
        $this->bancoMovimiento = $bancoMovimiento;
        return $this;
    }   

    public function getEfectivoMovimiento(): ?Efectivo
    {
        return $this->efectivoMovimiento;
    }

    public function setEfectivoMovimiento(?Efectivo $efectivoMovimiento): self
    {
        $this->efectivoMovimiento = $efectivoMovimiento;
        return $this;
    }   

    public function isAfectaCaja(): bool
    {
        return $this->afectaCaja;
    }

    public function setAfectaCaja(bool $afectaCaja): self
    {
        $this->afectaCaja = $afectaCaja;
        return $this;
    }

    public function getOrigen(): ?string
    {
        return $this->origen;
    }

    public function setOrigen(?string $origen): self
    {
        $this->origen = $origen;
        return $this;       
    }

    public function confirmar(): void
    {
        $this->estado = self::ESTADO_CONFIRMADO;
        $this->fechaConfirmado = new \DateTime();
        $this->marcarActualizado();
    }

    public function marcarPagado(): void
    {
        $this->estado = self::ESTADO_PAGADO;
        $this->fechaPagado = new \DateTime();
        $this->fechaReal = new \DateTime();
        $this->marcarActualizado();
    }

    public function cancelar(): void
    {
        $this->estado = self::ESTADO_CANCELADO;
        $this->marcarActualizado();
    }
    
    public function getFechaConfirmado(): ?\DateTimeInterface
    {
        return $this->fechaConfirmado;
    }

    public function setFechaConfirmado(?\DateTimeInterface $fechaConfirmado): self
    {
        $this->fechaConfirmado = $fechaConfirmado;
        return $this;
    }

    public function getFechaPagado(): ?\DateTimeInterface
    {
        return $this->fechaPagado;
    }

    public function setFechaPagado(?\DateTimeInterface $fechaPagado): self
    {
        $this->fechaPagado = $fechaPagado;
        return $this;
    }    

    public function getBasePrevista(): ?string
    {
        return $this->basePrevista;
    }

    public function setBasePrevista(?string $basePrevista): self
    {
        $this->basePrevista = $basePrevista;

        return $this;
    }

    public function getTipoIvaPrevisto(): ?string
    {
        return $this->tipoIvaPrevisto;
    }

    public function setTipoIvaPrevisto(?string $tipoIvaPrevisto): self
    {
        $this->tipoIvaPrevisto = $tipoIvaPrevisto;

        return $this;
    }

    public function getIvaPrevisto(): ?string
    {
        return $this->ivaPrevisto;
    }

    public function setIvaPrevisto(?string $ivaPrevisto): self
    {
        $this->ivaPrevisto = $ivaPrevisto;

        return $this;
    }

    public function getRecargoPrevisto(): ?string
    {
        return $this->recargoPrevisto;
    }

    public function setRecargoPrevisto(?string $recargoPrevisto): self
    {
        $this->recargoPrevisto = $recargoPrevisto;

        return $this;
    }

    public function getBaseReal(): ?string
    {
        return $this->baseReal;
    }

    public function setBaseReal(?string $baseReal): self
    {
        $this->baseReal = $baseReal;

        return $this;
    }

    public function getTipoIvaReal(): ?string
    {
        return $this->tipoIvaReal;
    }

    public function setTipoIvaReal(?string $tipoIvaReal): self
    {
        $this->tipoIvaReal = $tipoIvaReal;

        return $this;
    }

    public function getIvaReal(): ?string
    {
        return $this->ivaReal;
    }

    public function setIvaReal(?string $ivaReal): self
    {
        $this->ivaReal = $ivaReal;

        return $this;
    }

    public function getRecargoReal(): ?string
    {
        return $this->recargoReal;
    }

    public function setRecargoReal(?string $recargoReal): self
    {
        $this->recargoReal = $recargoReal;

        return $this;
    }

    public function isIvaDeducible(): bool
    {
        return $this->ivaDeducible;
    }

    public function setIvaDeducible(bool $ivaDeducible): self
    {
        $this->ivaDeducible = $ivaDeducible;

        return $this;
    }    

    public function esManual(): bool
    {
        return $this->origen === self::ORIGEN_MANUAL;
    }

}