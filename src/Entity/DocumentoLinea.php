<?php

namespace App\Entity;

use App\Repository\DocumentoLineaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD DOCUMENTOLINEA
 * ======================
 * Representa cada línea individual dentro de un Documento.
 * Es el corazón del sistema: permite facturar cualquier cosa,
 * tenga o no producto asociado en el catálogo.
 *
 * TIPOS DE LÍNEA:
 * ---------------
 * - producto:   Artículo del catálogo (Productos). Puede afectar al stock.
 *               Ejemplo: "Plato ducha Roca 80x80", "Mampara 90cm"
 *
 * - mano_obra:  Trabajo del equipo. Sin producto, sin stock.
 *               Ejemplo: "Demolición y saneamiento", "Alicatado"
 *
 * - servicio:   Servicio externo o propio sin producto físico.
 *               Ejemplo: "Gestión de residuos", "Transporte"
 *
 * - descuento:  Línea con importe negativo para aplicar descuentos globales.
 *               Ejemplo: "Descuento cliente habitual -5%"
 *
 * - comentario: Línea de texto sin importes. Para agrupar o aclarar.
 *               Ejemplo: "--- Instalación sanitarios ---"
 *
 * PRODUCTO NULLABLE:
 * ------------------
 * El campo producto es opcional. Cuando tipoLinea = mano_obra, servicio,
 * descuento o comentario, producto será null y descripcion lo explica todo.
 * Cuando tipoLinea = producto, se vincula a Productos pero descripcion
 * puede sobreescribirse (útil si el nombre del catálogo no es suficiente).
 *
 * CÁLCULO DE IMPORTES:
 * --------------------
 * El cálculo real lo hace DocumentoService, no esta entidad.
 * Esta entidad solo almacena los valores resultantes:
 *
 *   subtotal = cantidad × precioUnitario × (1 - descuento/100)
 *   totalIva = subtotal × (tipoIva/100)
 *   totalCoste = cantidad × costeUnitario
 *
 * STOCK:
 * ------
 * afectaStock = true solo tiene sentido cuando hay producto vinculado.
 * El movimiento de stock lo genera StockService, no esta entidad.
 * stockMovido evita generar el movimiento dos veces si se reprocesa.
 *
 * TRAZABILIDAD EN PRESUPUESTOS ADICIONALES:
 * -----------------------------------------
 * Cuando una línea viene de un presupuesto adicional aplicado a una factura,
 * origenPresupuesto apunta a ese presupuesto. Permite saber visualmente
 * en la factura qué líneas son del presupuesto inicial y cuáles de cada adicional.
 *
 * LÍNEAS NEGATIVAS:
 * -----------------
 * Para eliminar algo de una factura mediante presupuesto adicional,
 * se añade la misma línea con cantidad negativa.
 * Ejemplo: Mampara estándar retirada → cantidad = -1, precio = 380€ → subtotal = -380€
 */
#[ORM\Entity(repositoryClass: DocumentoLineaRepository::class)]
#[ORM\Table(name: 'documento_linea')]
class DocumentoLinea
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Documento al que pertenece esta línea.
     * Si el documento se elimina, todas sus líneas se eliminan (CASCADE).
     */
    #[ORM\ManyToOne(targetEntity: Documento::class, inversedBy: 'lineas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Documento $documento = null;

    /**
     * Orden visual de la línea dentro del documento.
     * Permite reordenar líneas sin cambiar su id.
     */
    #[ORM\Column(type: 'integer')]
    private int $posicion = 0;

    // -------------------------------------------------------------------------
    // TIPO Y DESCRIPCIÓN
    // -------------------------------------------------------------------------

    /**
     * Tipo de línea.
     * Valores: producto | mano_obra | servicio | descuento | comentario
     */
    #[ORM\Column(type: 'string', length: 20)]
    private ?string $tipoLinea = null;

    /**
     * Producto del catálogo. Nullable.
     * Solo se rellena cuando tipoLinea = producto.
     * Apunta a la entidad Productos existente (no se modifica).
     */
    #[ORM\ManyToOne(targetEntity: Productos::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Productos $producto = null;

    /**
     * Descripción visible en el documento.
     * Obligatoria siempre. Si hay producto vinculado, se puede copiar
     * su descripcion_Pd o personalizarla para este documento concreto.
     */
    #[ORM\Column(type: 'text')]
    private ?string $descripcion = null;

    // -------------------------------------------------------------------------
    // CANTIDADES Y PRECIOS
    // -------------------------------------------------------------------------

    /**
     * Cantidad de la línea.
     * Puede ser decimal (2.5 m2, 3.75 horas).
     * Puede ser negativa en líneas de descuento o retirada de elementos.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $cantidad = '1.000';

    /**
     * Unidad de medida. Campo libre.
     * Ejemplos: ud, m2, ml, h, kg, m3, día...
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $unidad = 'ud';

    /**
     * Precio de venta unitario sin IVA.
     * Lo que se cobra al cliente por unidad.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $precioUnitario = '0.00';

    /**
     * Coste real unitario sin IVA.
     * Lo que nos cuesta a nosotros: precio de compra del producto,
     * coste hora de mano de obra, etc.
     * Permite calcular el margen real línea a línea.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $costeUnitario = '0.00';

    /**
     * Porcentaje de descuento aplicado a esta línea.
     * Ejemplo: 10.00 = 10% de descuento.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $descuento = '0.00';

    /**
     * Tipo de IVA aplicado a esta línea en porcentaje.
     * Valores habituales: 0.00, 4.00, 10.00, 21.00
     * Se guarda por línea porque puede variar (obra = 10%, producto = 21%).
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $tipoIva = '21.00';

    // -------------------------------------------------------------------------
    // TOTALES CALCULADOS (los calcula DocumentoService, aquí solo se almacenan)
    // -------------------------------------------------------------------------

    /**
     * Importe neto de la línea sin IVA y con descuento aplicado.
     * subtotal = cantidad × precioUnitario × (1 - descuento/100)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $subtotal = '0.00';

    /**
     * Importe de IVA de esta línea.
     * totalIva = subtotal × (tipoIva/100)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalIva = '0.00';

    /**
     * Coste total de la línea.
     * totalCoste = cantidad × costeUnitario
     * Usado para calcular margen bruto del documento.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalCoste = '0.00';

    // -------------------------------------------------------------------------
    // STOCK
    // -------------------------------------------------------------------------

    /**
     * Indica si esta línea debe generar movimiento de stock al confirmar.
     * Solo tiene sentido cuando hay producto vinculado.
     * Puede ser false incluso con producto (ej: producto de exposición, servicio incluido).
     */
    #[ORM\Column(type: 'boolean')]
    private bool $afectaStock = false;

    /**
     * Indica si el movimiento de stock ya fue generado.
     * Evita duplicar movimientos si se reprocesa el documento.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $stockMovido = false;

    // -------------------------------------------------------------------------
    // TRAZABILIDAD
    // -------------------------------------------------------------------------

    /**
     * Presupuesto adicional del que proviene esta línea.
     * Null si es del presupuesto inicial.
     * Permite agrupar en la factura qué líneas vienen de cada adicional.
     */
    #[ORM\ManyToOne(targetEntity: Documento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Documento $origenPresupuesto = null;

    // -------------------------------------------------------------------------
    // GETTERS Y SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumento(): ?Documento
    {
        return $this->documento;
    }

    public function setDocumento(?Documento $documento): static
    {
        $this->documento = $documento;
        return $this;
    }

    public function getPosicion(): int
    {
        return $this->posicion;
    }

    public function setPosicion(int $posicion): static
    {
        $this->posicion = $posicion;
        return $this;
    }

    public function getTipoLinea(): ?string
    {
        return $this->tipoLinea;
    }

    public function setTipoLinea(string $tipoLinea): static
    {
        $this->tipoLinea = $tipoLinea;
        return $this;
    }

    public function getProducto(): ?Productos
    {
        return $this->producto;
    }

    public function setProducto(?Productos $producto): static
    {
        $this->producto = $producto;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getCantidad(): string
    {
        return $this->cantidad;
    }

    public function setCantidad(string $cantidad): static
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function getUnidad(): string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = $unidad;
        return $this;
    }

    public function getPrecioUnitario(): string
    {
        return $this->precioUnitario;
    }

    public function setPrecioUnitario(string $precioUnitario): static
    {
        $this->precioUnitario = $precioUnitario;
        return $this;
    }

    public function getCosteUnitario(): string
    {
        return $this->costeUnitario;
    }

    public function setCosteUnitario(string $costeUnitario): static
    {
        $this->costeUnitario = $costeUnitario;
        return $this;
    }

    public function getDescuento(): string
    {
        return $this->descuento;
    }

    public function setDescuento(string $descuento): static
    {
        $this->descuento = $descuento;
        return $this;
    }

    public function getTipoIva(): string
    {
        return $this->tipoIva;
    }

    public function setTipoIva(string $tipoIva): static
    {
        $this->tipoIva = $tipoIva;
        return $this;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTotalIva(): string
    {
        return $this->totalIva;
    }

    public function setTotalIva(string $totalIva): static
    {
        $this->totalIva = $totalIva;
        return $this;
    }

    public function getTotalCoste(): string
    {
        return $this->totalCoste;
    }

    public function setTotalCoste(string $totalCoste): static
    {
        $this->totalCoste = $totalCoste;
        return $this;
    }

    public function isAfectaStock(): bool
    {
        return $this->afectaStock;
    }

    public function setAfectaStock(bool $afectaStock): static
    {
        $this->afectaStock = $afectaStock;
        return $this;
    }

    public function isStockMovido(): bool
    {
        return $this->stockMovido;
    }

    public function setStockMovido(bool $stockMovido): static
    {
        $this->stockMovido = $stockMovido;
        return $this;
    }

    public function getOrigenPresupuesto(): ?Documento
    {
        return $this->origenPresupuesto;
    }

    public function setOrigenPresupuesto(?Documento $origenPresupuesto): static
    {
        $this->origenPresupuesto = $origenPresupuesto;
        return $this;
    }
}