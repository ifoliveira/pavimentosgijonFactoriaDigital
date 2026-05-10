<?php

namespace App\Entity;

use App\Repository\FacturaProveedorRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ENTIDAD FACTURA PROVEEDOR
 * =========================
 * Representa una factura recibida de un proveedor (materiales, servicios, etc).
 *
 * Es el punto de entrada de los costes reales del negocio.
 *
 * Esta entidad NO está ligada directamente a proyectos ni a costes de obra,
 * sino que actúa como contenedor del documento original.
 *
 * FLUJO DE NEGOCIO:
 * ------------------
 * 1. Se recibe una factura (PDF)
 * 2. Se analiza (OCR / IA)
 * 3. Se guarda:
 *      - Cabecera (esta entidad)
 *      - Líneas (FacturaProveedorLinea)
 *      - JSON original del análisis
 * 4. Se generan vencimientos → Forecast
 * 5. Opcionalmente, se asignan líneas a proyectos → ProyectoGasto
 *
 * IMPORTANTE:
 * ------------
 * - Esta entidad NO genera directamente costes de obra.
 * - Los costes de obra se generan SOLO cuando una línea se asigna a un proyecto.
 * - Una factura puede:
 *      ✔ Ir completa a una obra
 *      ✔ Repartirse entre varias obras
 *      ✔ No pertenecer a ninguna obra (stock / tienda / gastos generales)
 *
 * ESTADO DE ASIGNACIÓN:
 * ---------------------
 * pendiente → ninguna línea asignada
 * parcial   → algunas líneas asignadas
 * asignada  → todas las líneas asignadas o ignoradas
 *
 * JSON ORIGINAL:
 * ---------------
 * Se guarda el resultado completo del OCR/IA para:
 *   - poder reprocesar en el futuro
 *   - depurar errores
 *   - mejorar el sistema sin perder datos
 *
 * RELACIÓN CON OTRAS ENTIDADES:
 * -----------------------------
 * - Tiene muchas FacturaProveedorLinea
 * - Genera movimientos en Forecast (vencimientos)
 * - Puede generar ProyectoGasto indirectamente (a través de líneas)
 */
#[ORM\Entity]
class FacturaProveedor
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // -------------------------------------------------------------------------
    // DATOS BÁSICOS DE LA FACTURA
    // -------------------------------------------------------------------------

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $proveedorNombre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroFactura = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaFactura = null;

    // -------------------------------------------------------------------------
    // IMPORTES
    // -------------------------------------------------------------------------

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalBase = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalIva = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalFactura = null;

    // -------------------------------------------------------------------------
    // DATOS DE ANÁLISIS
    // -------------------------------------------------------------------------

    /**
     * JSON completo generado por el OCR / IA
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $jsonOriginal = null;

    /**
     * Estado de asignación de las líneas
     */
    #[ORM\Column(length: 50)]
    private string $estadoAsignacion = 'pendiente';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $fechaCreacion;

    // -------------------------------------------------------------------------
    // RELACIONES
    // -------------------------------------------------------------------------

    /**
     * Líneas detectadas en la factura
     */
    #[ORM\OneToMany(
        mappedBy: 'facturaProveedor',
        targetEntity: FacturaProveedorLinea::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $lineas;

    public function __construct()
    {
        $this->lineas = new ArrayCollection();
        $this->fechaCreacion = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // DOCUMENTO ORIGINAL
    // -------------------------------------------------------------------------

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombreArchivoOriginal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rutaPdf = null;

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    // ---------------- PROVEEDOR ----------------

    public function getProveedorNombre(): ?string
    {
        return $this->proveedorNombre;
    }

    public function setProveedorNombre(?string $proveedorNombre): self
    {
        $this->proveedorNombre = $proveedorNombre;
        return $this;
    }

    // ---------------- NUMERO FACTURA ----------------

    public function getNumeroFactura(): ?string
    {
        return $this->numeroFactura;
    }

    public function setNumeroFactura(?string $numeroFactura): self
    {
        $this->numeroFactura = $numeroFactura;
        return $this;
    }

    // ---------------- FECHA ----------------

    public function getFechaFactura(): ?\DateTimeInterface
    {
        return $this->fechaFactura;
    }

    public function setFechaFactura(?\DateTimeInterface $fechaFactura): self
    {
        $this->fechaFactura = $fechaFactura;
        return $this;
    }

    // ---------------- BASE ----------------

    public function getTotalBase(): ?float
    {
        return $this->totalBase;
    }

    public function setTotalBase(?float $totalBase): self
    {
        $this->totalBase = $totalBase;
        return $this;
    }

    // ---------------- IVA ----------------

    public function getTotalIva(): ?float
    {
        return $this->totalIva;
    }

    public function setTotalIva(?float $totalIva): self
    {
        $this->totalIva = $totalIva;
        return $this;
    }

    // ---------------- TOTAL FACTURA ----------------

    public function getTotalFactura(): ?float
    {
        return $this->totalFactura;
    }

    public function setTotalFactura(?float $totalFactura): self
    {
        $this->totalFactura = $totalFactura;
        return $this;
    }

    // ---------------- JSON ORIGINAL ----------------

    public function getJsonOriginal(): ?array
    {
        return $this->jsonOriginal;
    }

    public function setJsonOriginal(?array $jsonOriginal): self
    {
        $this->jsonOriginal = $jsonOriginal;
        return $this;
    }

    // ---------------- ESTADO ASIGNACION ----------------

    public function getEstadoAsignacion(): string
    {
        return $this->estadoAsignacion;
    }

    public function setEstadoAsignacion(string $estadoAsignacion): self
    {
        $this->estadoAsignacion = $estadoAsignacion;
        return $this;
    }

    // ---------------- FECHA CREACION ----------------

    public function getFechaCreacion(): \DateTimeInterface
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTimeInterface $fechaCreacion): self
    {
        $this->fechaCreacion = $fechaCreacion;
        return $this;
    }

    // ---------------- LINEAS ----------------

    /**
     * @return Collection<int, FacturaProveedorLinea>
     */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(FacturaProveedorLinea $linea): self
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setFacturaProveedor($this);
        }

        return $this;
    }

    public function removeLinea(FacturaProveedorLinea $linea): self
    {
        if ($this->lineas->removeElement($linea)) {
            if ($linea->getFacturaProveedor() === $this) {
                $linea->setFacturaProveedor(null);
            }
        }

        return $this;
    }

    public function getNombreArchivoOriginal(): ?string
    {
        return $this->nombreArchivoOriginal;
    }

    public function setNombreArchivoOriginal(?string $nombreArchivoOriginal): self
    {
        $this->nombreArchivoOriginal = $nombreArchivoOriginal;
        return $this;
    }

    public function getRutaPdf(): ?string
    {
        return $this->rutaPdf;
    }

    public function setRutaPdf(?string $rutaPdf): self
    {
        $this->rutaPdf = $rutaPdf;
        return $this;
    }
}