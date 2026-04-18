<?php

namespace App\Entity;

use App\Repository\CosteGremioRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD COSTE GREMIO
 * =====================
 * Registra los costes de mano de obra externa (gremios) asociados a un proyecto.
 * Es un control interno del negocio, no contabilidad formal.
 *
 * Su propósito principal es conocer el margen real de cada obra:
 *
 *   Margen obra = Documento.baseImponible - suma(CosteGremio.importeReal)
 *
 * ESTIMADO VS REAL:
 * -----------------
 * El coste se registra en dos momentos distintos:
 *
 *   importeEstimado → lo que creemos que nos va a costar cuando planificamos.
 *                     Se rellena al crear el proyecto o el presupuesto.
 *                     Es la base para calcular el margen previsto.
 *
 *   importeReal     → lo que realmente nos cobra el gremio al terminar.
 *                     Puede coincidir o no con el estimado.
 *                     Se rellena cuando llega la factura o el acuerdo final.
 *                     Null hasta que se conoce el importe definitivo.
 *
 * ESTADO DEL PAGO:
 * ----------------
 * Valores: pendiente | pagado | cancelado
 *
 *   pendiente → el gremio ha terminado pero aún no hemos pagado
 *   pagado    → pagado, con o sin factura del gremio
 *   cancelado → el gremio no realizó el trabajo
 *
 * FACTURA DEL GREMIO:
 * -------------------
 * No siempre emiten factura. El campo numeroFacturaGremio es opcional
 * y sirve para anotar la referencia si la hay.
 * En el futuro, si se implementa FacturaProveedor, se añadirá
 * una relación formal sin romper esta estructura.
 *
 * MÉTODO DE PAGO:
 * ---------------
 * Cuando estadoPago = pagado se registra cómo se pagó:
 *   efectivo     → pago en mano, sin vinculación a Banco
 *   transferencia → vinculado a registro Banco
 *   bizum        → vinculado a registro Banco
 *
 * EJEMPLO REAL:
 * -------------
 * Proyecto: "Reforma baño García"
 *
 *   CosteGremio: Fontanero Pérez
 *     importeEstimado = 400€
 *     importeReal     = 380€  (negoció a la baja)
 *     estadoPago      = pagado
 *     metodoPago      = transferencia
 *     banco           → registro Banco correspondiente
 *
 *   CosteGremio: Alicatador Martínez
 *     importeEstimado = 600€
 *     importeReal     = 650€  (hubo más superficie de lo previsto)
 *     estadoPago      = pendiente
 *     metodoPago      = null  (aún no pagado)
 *
 *   Margen previsto:  2.290€ - (400€ + 600€) = 1.290€
 *   Margen real:      2.290€ - (380€ + 650€) = 1.260€
 */
#[ORM\Entity(repositoryClass: CosteGremioRepository::class)]
#[ORM\Table(name: 'coste_gremio')]
class CosteGremio
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Proyecto al que pertenece este coste.
     * Si el proyecto se elimina, se eliminan sus costes de gremio.
     */
    #[ORM\ManyToOne(targetEntity: Proyecto::class, inversedBy: 'costesGremio')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proyecto $proyecto = null;

    // -------------------------------------------------------------------------
    // GREMIO
    // -------------------------------------------------------------------------

    /**
     * Nombre del gremio o empresa externa.
     * Campo libre: "Fontanero Pérez", "Alicatados Martínez S.L.", etc.
     */
    #[ORM\Column(type: 'string', length: 150)]
    private ?string $gremio = null;

    /**
     * Descripción del trabajo realizado.
     * Ejemplo: "Instalación fontanería baño completo"
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $concepto = null;

    // -------------------------------------------------------------------------
    // IMPORTES
    // -------------------------------------------------------------------------

    /**
     * Coste estimado antes de la obra.
     * Base para calcular el margen previsto del proyecto.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeEstimado = '0.00';

    /**
     * Coste real acordado o facturado por el gremio.
     * Null hasta que se conoce el importe definitivo.
     * Puede diferir del estimado.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $importeReal = null;

    // -------------------------------------------------------------------------
    // ESTADO Y PAGO
    // -------------------------------------------------------------------------

    /**
     * Estado del pago al gremio.
     * Valores: pendiente | pagado | cancelado
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $estadoPago = 'pendiente';

    /**
     * Método de pago utilizado cuando estadoPago = pagado.
     * Valores: efectivo | transferencia | bizum
     * Null mientras no se haya pagado.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $metodoPago = null;

    /**
     * Fecha en que se realizó el pago.
     * Null mientras no se haya pagado.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaPago = null;

    /**
     * Referencia de la factura emitida por el gremio.
     * Opcional: no todos los gremios emiten factura.
     * Ejemplo: "F-2025-034", "Recibo 12/03/2025"
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $numeroFacturaGremio = null;

    /**
     * Vinculación con movimiento bancario.
     * Solo se rellena cuando metodoPago = transferencia o bizum
     * y el pago ya aparece en el extracto bancario.
     * Null si el pago fue en efectivo.
     */
    #[ORM\ManyToOne(targetEntity: Banco::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Banco $banco = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    // -------------------------------------------------------------------------
    // TIMESTAMPS
    // -------------------------------------------------------------------------

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $creadoEn = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    // -------------------------------------------------------------------------
    // CONSTRUCTOR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->creadoEn = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // LIFECYCLE CALLBACKS
    // -------------------------------------------------------------------------

    #[ORM\HasLifecycleCallbacks]
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->actualizadoEn = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // GETTERS Y SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProyecto(): ?Proyecto
    {
        return $this->proyecto;
    }

    public function setProyecto(?Proyecto $proyecto): static
    {
        $this->proyecto = $proyecto;
        return $this;
    }

    public function getGremio(): ?string
    {
        return $this->gremio;
    }

    public function setGremio(string $gremio): static
    {
        $this->gremio = $gremio;
        return $this;
    }

    public function getConcepto(): ?string
    {
        return $this->concepto;
    }

    public function setConcepto(?string $concepto): static
    {
        $this->concepto = $concepto;
        return $this;
    }

    public function getImporteEstimado(): string
    {
        return $this->importeEstimado;
    }

    public function setImporteEstimado(string $importeEstimado): static
    {
        $this->importeEstimado = $importeEstimado;
        return $this;
    }

    public function getImporteReal(): ?string
    {
        return $this->importeReal;
    }

    public function setImporteReal(?string $importeReal): static
    {
        $this->importeReal = $importeReal;
        return $this;
    }

    public function getEstadoPago(): string
    {
        return $this->estadoPago;
    }

    public function setEstadoPago(string $estadoPago): static
    {
        $this->estadoPago = $estadoPago;
        return $this;
    }

    public function getMetodoPago(): ?string
    {
        return $this->metodoPago;
    }

    public function setMetodoPago(?string $metodoPago): static
    {
        $this->metodoPago = $metodoPago;
        return $this;
    }

    public function getFechaPago(): ?\DateTimeInterface
    {
        return $this->fechaPago;
    }

    public function setFechaPago(?\DateTimeInterface $fechaPago): static
    {
        $this->fechaPago = $fechaPago;
        return $this;
    }

    public function getNumeroFacturaGremio(): ?string
    {
        return $this->numeroFacturaGremio;
    }

    public function setNumeroFacturaGremio(?string $numeroFacturaGremio): static
    {
        $this->numeroFacturaGremio = $numeroFacturaGremio;
        return $this;
    }

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setBanco(?Banco $banco): static
    {
        $this->banco = $banco;
        return $this;
    }

    public function getNotas(): ?string
    {
        return $this->notas;
    }

    public function setNotas(?string $notas): static
    {
        $this->notas = $notas;
        return $this;
    }

    public function getCreadoEn(): ?\DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }

    /**
     * Devuelve el importe efectivo a usar para cálculos de margen.
     * Si ya se conoce el real, usa ese. Si no, usa el estimado.
     */
    public function getImporteEfectivo(): string
    {
        return $this->importeReal ?? $this->importeEstimado;
    }
}