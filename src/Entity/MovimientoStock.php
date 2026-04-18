<?php

namespace App\Entity;

use App\Repository\MovimientoStockRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD MOVIMIENTOSTOCK
 * ========================
 * Registra cada variación de stock de un producto.
 * Es un log inmutable: nunca se edita ni se borra un movimiento.
 * Si hay un error, se crea un movimiento correctivo.
 *
 * El stock actual de un producto en cualquier momento es la suma
 * de todos sus movimientos (entradas - salidas + ajustes).
 * Productos.stock_Pd queda como caché desnormalizada de esa suma.
 *
 * TIPOS DE MOVIMIENTO:
 * --------------------
 * - entrada:    Recepción de mercancía. Manual por ahora.
 *               Ejemplo: llegan 5 platos de ducha del proveedor.
 *
 * - salida:     Producto vendido y entregado al cliente.
 *               Siempre vinculado a una DocumentoLinea.
 *               Solo se genera cuando DocumentoLinea.afectaStock = true
 *               y DocumentoLinea.stockMovido = false.
 *
 * - ajuste:     Corrección tras recuento físico.
 *               Puede ser positivo o negativo.
 *               Ejemplo: había 3 en sistema pero físicamente hay 2 → ajuste -1
 *
 * - devolucion: Cliente devuelve un producto. Revierte una salida.
 *               Vinculado a la DocumentoLinea original si es posible.
 *
 * INMUTABILIDAD:
 * --------------
 * Un movimiento registrado no se modifica nunca.
 * Si se anuló una factura por error, se crea un movimiento
 * de tipo 'devolucion' que compensa la salida original.
 * Esto garantiza trazabilidad completa del historial de stock.
 *
 * SNAPSHOT DE STOCK:
 * ------------------
 * stockResultante guarda el stock del producto DESPUÉS de aplicar
 * este movimiento. Permite reconstruir el histórico sin recalcular
 * toda la cadena de movimientos.
 *
 * ORIGEN DEL MOVIMIENTO:
 * ----------------------
 * Se registra de dónde viene cada movimiento para trazabilidad:
 *   documentoLinea → venta (salida) o devolución vinculada a factura
 *   null           → entrada manual o ajuste de inventario
 *
 * En el futuro, cuando se implemente FacturaProveedor, se añadirá
 * un campo facturaProveedor nullable sin romper esta estructura.
 */
#[ORM\Entity(repositoryClass: MovimientoStockRepository::class)]
#[ORM\Table(name: 'movimiento_stock')]
class MovimientoStock
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Producto cuyo stock varía.
     * SET NULL si el producto se elimina: conservamos el historial
     * aunque el producto ya no exista en catálogo.
     */
    #[ORM\ManyToOne(targetEntity: Productos::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Productos $producto = null;

    // -------------------------------------------------------------------------
    // TIPO Y CANTIDAD
    // -------------------------------------------------------------------------

    /**
     * Tipo de movimiento.
     * Valores: entrada | salida | ajuste | devolucion
     */
    #[ORM\Column(type: 'string', length: 20)]
    private ?string $tipo = null;

    /**
     * Cantidad que varía. Siempre positiva.
     * El tipo determina si suma o resta al stock:
     *   entrada   → suma
     *   salida    → resta
     *   devolucion → suma
     *   ajuste    → puede ser positivo o negativo (usar campo ajusteNegativo)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $cantidad = '0.000';

    /**
     * Solo relevante cuando tipo = ajuste.
     * true  → el ajuste resta stock (había más de lo real)
     * false → el ajuste suma stock (había menos de lo real)
     */
    #[ORM\Column(type: 'boolean')]
    private bool $ajusteNegativo = false;

    // -------------------------------------------------------------------------
    // COSTE
    // -------------------------------------------------------------------------

    /**
     * Coste unitario del producto en este movimiento.
     * En entradas: precio de compra al proveedor.
     * En salidas: coste registrado en DocumentoLinea.costeUnitario.
     * Permite calcular el valor del inventario y el coste real de ventas.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $costeUnitario = '0.00';

    // -------------------------------------------------------------------------
    // SNAPSHOT
    // -------------------------------------------------------------------------

    /**
     * Stock resultante del producto después de aplicar este movimiento.
     * Ejemplo: había 5, salida de 2 → stockResultante = 3
     * Calculado por StockService en el momento de registrar el movimiento.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $stockResultante = '0.000';

    // -------------------------------------------------------------------------
    // FECHAS
    // -------------------------------------------------------------------------

    /**
     * Fecha real del movimiento (cuándo ocurrió físicamente).
     * Puede diferir de creadoEn si se registra con retraso.
     */
    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $creadoEn = null;

    // -------------------------------------------------------------------------
    // TRAZABILIDAD
    // -------------------------------------------------------------------------

    /**
     * Línea de documento que originó este movimiento.
     * Relleno en salidas (venta) y devoluciones vinculadas a factura.
     * Null en entradas manuales y ajustes de inventario.
     *
     * SET NULL si se borra la línea: conservamos el movimiento
     * aunque el documento haya sido eliminado.
     */
    #[ORM\ManyToOne(targetEntity: DocumentoLinea::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DocumentoLinea $documentoLinea = null;

    /**
     * Motivo del movimiento. Obligatorio en ajustes, opcional en el resto.
     * Ejemplos:
     *   ajuste    → "Recuento físico enero 2025, diferencia de 1 unidad"
     *   entrada   → "Recepción pedido proveedor Roca"
     *   devolucion → "Cliente devuelve mampara defectuosa"
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motivo = null;

    // -------------------------------------------------------------------------
    // CONSTRUCTOR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->fecha = new \DateTime();
        $this->creadoEn = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // GETTERS Y SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;
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

    public function isAjusteNegativo(): bool
    {
        return $this->ajusteNegativo;
    }

    public function setAjusteNegativo(bool $ajusteNegativo): static
    {
        $this->ajusteNegativo = $ajusteNegativo;
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

    public function getStockResultante(): string
    {
        return $this->stockResultante;
    }

    public function setStockResultante(string $stockResultante): static
    {
        $this->stockResultante = $stockResultante;
        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): static
    {
        $this->fecha = $fecha;
        return $this;
    }

    public function getCreadoEn(): ?\DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function getDocumentoLinea(): ?DocumentoLinea
    {
        return $this->documentoLinea;
    }

    public function setDocumentoLinea(?DocumentoLinea $documentoLinea): static
    {
        $this->documentoLinea = $documentoLinea;
        return $this;
    }

    public function getMotivo(): ?string
    {
        return $this->motivo;
    }

    public function setMotivo(?string $motivo): static
    {
        $this->motivo = $motivo;
        return $this;
    }
}