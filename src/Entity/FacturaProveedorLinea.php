<?php

namespace App\Entity;

use App\Repository\FacturaProveedorLineaRepository;
use App\Entity\FacturaProveedorLineaAsignacion;
use App\Entity\FacturaProveedor;
use App\Entity\Proyecto;
use App\Entity\Productos;
use App\Entity\ProyectoGasto;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ENTIDAD FACTURA PROVEEDOR LINEA
 * ===============================
 * Representa una línea individual dentro de una factura de proveedor.
 *
 * Es la unidad mínima de decisión para asignar costes.
 *
 * Esta entidad es CLAVE para el sistema porque permite:
 *   - Repartir una factura entre varias obras
 *   - Separar materiales, transporte, servicios, etc
 *   - Decidir qué parte impacta en cada proyecto
 *
 * FLUJO DE NEGOCIO:
 * ------------------
 * 1. Se detecta automáticamente desde el OCR/IA
 * 2. Inicialmente queda en estado "pendiente"
 * 3. El usuario decide:
 *
 *    → Asignar a proyecto → genera ProyectoGasto
 *    → Marcar como stock → no afecta a obra
 *    → Marcar como gasto general → no va a proyecto
 *    → Ignorar → no se usa
 *
 * IMPORTANTE:
 * ------------
 * - NO todas las líneas generan coste de proyecto
 * - SOLO generan coste cuando:
 *      ✔ tienen proyecto asignado
 *      ✔ se marca como tipoDestino = "obra"
 *
 * TIPOS DE DESTINO:
 * -----------------
 * obra     → genera ProyectoGasto
 * stock    → producto para tienda/exposición
 * general  → gasto general del negocio
 * ignorar  → no se tiene en cuenta
 *
 * ESTADO:
 * --------
 * pendiente → sin procesar
 * asignada  → ya vinculada (normalmente a proyecto)
 * ignorada  → descartada
 *
 * RELACIONES:
 * ------------
 * - Pertenece a una FacturaProveedor
 * - Puede estar vinculada a un Proyecto
 * - Puede generar un ProyectoGasto
 * - Puede vincularse a un Producto (opcional)
 *
 * EJEMPLO REAL:
 * -------------
 * Factura proveedor:
 *   Mampara Yoko         → 815€ → Proyecto A → gasto de obra
 *   Mueble lavabo        → 350€ → Proyecto B → gasto de obra
 *   Transporte           → 35€  → general     → gasto negocio
 *   Producto exposición  → 200€ → stock       → tienda
 */
#[ORM\Entity(repositoryClass: FacturaProveedorLineaRepository::class)]
class FacturaProveedorLinea
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // -------------------------------------------------------------------------
    // RELACIÓN PRINCIPAL
    // -------------------------------------------------------------------------

    #[ORM\ManyToOne(inversedBy: 'lineas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FacturaProveedor $facturaProveedor = null;

    // -------------------------------------------------------------------------
    // DATOS DE LA LÍNEA
    // -------------------------------------------------------------------------

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $cantidad = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $precioUnitario = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $base = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $iva = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $total = null;

    // -------------------------------------------------------------------------
    // ASIGNACIÓN DE NEGOCIO
    // -------------------------------------------------------------------------



    /**
     * Productos detectado (opcional)
     */
    #[ORM\ManyToOne]
    private ?Productos $producto = null;

    /**
     * Tipo de destino de la línea
     * (obra, stock, general, ignorar)
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tipoDestino = null;

    /**
     * Estado de procesamiento
     */
    #[ORM\Column(length: 50)]
    private string $estado = 'pendiente';

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importeBruto = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $porcentajeIva = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importeIva = null;

    #[ORM\Column(type: 'boolean')]
    private bool $tieneRecargoEquivalencia = false;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $porcentajeRecargoEquivalencia = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $importeRecargoEquivalencia = null;

    #[ORM\OneToMany(
        mappedBy: 'linea',
        targetEntity: FacturaProveedorLineaAsignacion::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $asignaciones;    
        
    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    // ---------------- FACTURA PROVEEDOR ----------------

    public function getFacturaProveedor(): ?FacturaProveedor
    {
        return $this->facturaProveedor;
    }

    public function setFacturaProveedor(?FacturaProveedor $facturaProveedor): self
    {
        $this->facturaProveedor = $facturaProveedor;
        return $this;
    }

    // ---------------- DESCRIPCIÓN ----------------

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    // ---------------- CANTIDAD ----------------

    public function getCantidad(): ?float
    {
        return $this->cantidad;
    }

    public function setCantidad(?float $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    // ---------------- PRECIO UNITARIO ----------------

    public function getPrecioUnitario(): ?float
    {
        return $this->precioUnitario;
    }

    public function setPrecioUnitario(?float $precioUnitario): self
    {
        $this->precioUnitario = $precioUnitario;
        return $this;
    }

    // ---------------- BASE ----------------

    public function getBase(): ?float
    {
        return $this->base;
    }

    public function setBase(?float $base): self
    {
        $this->base = $base;
        return $this;
    }

    // ---------------- IVA ----------------

    public function getIva(): ?float
    {
        return $this->iva;
    }

    public function setIva(?float $iva): self
    {
        $this->iva = $iva;
        return $this;
    }

    // ---------------- TOTAL ----------------

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(?float $total): self
    {
        $this->total = $total;
        return $this;
    }


    // ---------------- PRODUCTO ----------------

    public function getProducto(): ?Productos
    {
        return $this->producto;
    }

    public function setProducto(?Productos $producto): self
    {
        $this->producto = $producto;
        return $this;
    }

    // ---------------- TIPO DESTINO ----------------

    public function getTipoDestino(): ?string
    {
        return $this->tipoDestino;
    }

    public function setTipoDestino(?string $tipoDestino): self
    {
        $this->tipoDestino = $tipoDestino;
        return $this;
    }

    // ---------------- ESTADO ----------------

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        return $this;
    }


    public function getImporteBruto(): ?float
    {
        return $this->importeBruto;
    }

    public function setImporteBruto(?float $importeBruto): self
    {
        $this->importeBruto = $importeBruto;
        return $this;
    }

    public function getPorcentajeIva(): ?float
    {
        return $this->porcentajeIva;
    }

    public function setPorcentajeIva(?float $porcentajeIva): self
    {
        $this->porcentajeIva = $porcentajeIva;
        return $this;
    }

    public function getImporteIva(): ?float
    {
        return $this->importeIva;
    }

    public function setImporteIva(?float $importeIva): self
    {
        $this->importeIva = $importeIva;
        return $this;
    }

    public function isTieneRecargoEquivalencia(): bool
    {
        return $this->tieneRecargoEquivalencia;
    }

    public function setTieneRecargoEquivalencia(bool $tieneRecargoEquivalencia): self
    {
        $this->tieneRecargoEquivalencia = $tieneRecargoEquivalencia;
        return $this;
    }

    public function getPorcentajeRecargoEquivalencia(): ?float
    {
        return $this->porcentajeRecargoEquivalencia;
    }

    public function setPorcentajeRecargoEquivalencia(?float $porcentajeRecargoEquivalencia): self
    {
        $this->porcentajeRecargoEquivalencia = $porcentajeRecargoEquivalencia;
        return $this;
    }

    public function getImporteRecargoEquivalencia(): ?float
    {
        return $this->importeRecargoEquivalencia;
    }

    public function setImporteRecargoEquivalencia(?float $importeRecargoEquivalencia): self
    {
        $this->importeRecargoEquivalencia = $importeRecargoEquivalencia;
        return $this;
    }

    /**
     * @return Collection<int, FacturaProveedorLineaAsignacion>
     */
    public function getAsignaciones(): Collection
    {
        return $this->asignaciones;
    }    
}