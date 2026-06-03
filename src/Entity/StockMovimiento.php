<?php

namespace App\Entity;

use App\Entity\Traits\FiscalTrait;
use App\Repository\StockMovimientoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovimientoRepository::class)]
class StockMovimiento
{
    use FiscalTrait;

    public const TIPO_ENTRADA_FACTURA = 'entrada_factura';
    public const TIPO_ENTRADA_MANUAL = 'entrada_manual';
    public const TIPO_SALIDA_OBRA = 'salida_obra';
    public const TIPO_SALIDA_TIENDA = 'salida_tienda';
    public const TIPO_AJUSTE_POSITIVO = 'ajuste_positivo';
    public const TIPO_AJUSTE_NEGATIVO = 'ajuste_negativo';
    public const TIPO_INVENTARIO_INICIAL = 'inventario_inicial';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Producto del catálogo asociado al movimiento, si se ha podido identificar.
    // No es obligatorio para evitar duplicar productos al importar facturas de proveedor.
    #[ORM\ManyToOne(targetEntity: CatalogoProducto::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CatalogoProducto $producto = null;

    // Descripción del producto en el momento del movimiento.
    // Sirve aunque no exista todavía relación con CatalogoProducto.
    #[ORM\Column(length: 255)]
    private ?string $descripcionProducto = null;

    // Referencia del proveedor, si existe.
    // Ayuda a enlazar posteriormente con el catálogo.
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenciaProveedor = null;

    // Tipo de movimiento: entrada por factura, salida a obra, ajuste, inventario inicial, etc.
    #[ORM\Column(length: 50)]
    private ?string $tipoMovimiento = null;

    // Cantidad del producto que entra o sale.
    // Siempre la guardamos en positivo; el tipoMovimiento indica si suma o resta.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $cantidad = null;

    // Fecha del movimiento de stock.
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha = null;

    // Si el movimiento viene de una factura de proveedor, aquí queda trazado.
    // Es nullable porque puede haber inventario inicial, ajustes o entradas manuales.
    #[ORM\ManyToOne(targetEntity: FacturaProveedorLineaAsignacion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FacturaProveedorLineaAsignacion $facturaProveedorLineaAsignacion = null;

    // Proyecto al que se imputa el producto cuando sale de stock para una obra.
    // Solo se usará normalmente en tipoMovimiento = salida_obra.
    #[ORM\ManyToOne(targetEntity: Proyecto::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Proyecto $proyecto = null;

    // Observaciones libres: inventario inicial, regularización, rotura, diferencia de recuento, etc.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    // Fecha de creación del registro.
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creadoEn = null;

    // Fecha de última actualización del registro.
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    public function __construct()
    {
        $this->fecha = new \DateTime();
        $this->creadoEn = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProducto(): ?CatalogoProducto
    {
        return $this->producto;
    }

    public function setProducto(?CatalogoProducto $producto): static
    {
        $this->producto = $producto;

        return $this;
    }

    public function getDescripcionProducto(): ?string
    {
        return $this->descripcionProducto;
    }

    public function setDescripcionProducto(string $descripcionProducto): static
    {
        $this->descripcionProducto = $descripcionProducto;

        return $this;
    }

    public function getReferenciaProveedor(): ?string
    {
        return $this->referenciaProveedor;
    }

    public function setReferenciaProveedor(?string $referenciaProveedor): static
    {
        $this->referenciaProveedor = $referenciaProveedor;

        return $this;
    }

    public function getTipoMovimiento(): ?string
    {
        return $this->tipoMovimiento;
    }

    public function setTipoMovimiento(string $tipoMovimiento): static
    {
        $this->tipoMovimiento = $tipoMovimiento;

        return $this;
    }

    public function getCantidad(): ?string
    {
        return $this->cantidad;
    }

    public function setCantidad(string|float|int $cantidad): static
    {
        $this->cantidad = (string) $cantidad;

        return $this;
    }

    // Importe total del movimiento de stock.
    public function getImporteTotalCoste(): float
    {
        return (float) $this->cantidad * (float) $this->getPrecioCosteUnitario();
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

    public function getFacturaProveedorLineaAsignacion(): ?FacturaProveedorLineaAsignacion
    {
        return $this->facturaProveedorLineaAsignacion;
    }

    public function setFacturaProveedorLineaAsignacion(?FacturaProveedorLineaAsignacion $facturaProveedorLineaAsignacion): static
    {
        $this->facturaProveedorLineaAsignacion = $facturaProveedorLineaAsignacion;

        return $this;
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

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): static
    {
        $this->observaciones = $observaciones;

        return $this;
    }

    public function getCreadoEn(): ?\DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function setCreadoEn(\DateTimeInterface $creadoEn): static
    {
        $this->creadoEn = $creadoEn;

        return $this;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }

    public function setActualizadoEn(?\DateTimeInterface $actualizadoEn): static
    {
        $this->actualizadoEn = $actualizadoEn;

        return $this;
    }

    // Indica si el movimiento suma stock.
    public function esEntrada(): bool
    {
        return in_array($this->tipoMovimiento, [
            self::TIPO_ENTRADA_FACTURA,
            self::TIPO_ENTRADA_MANUAL,
            self::TIPO_AJUSTE_POSITIVO,
            self::TIPO_INVENTARIO_INICIAL,
        ], true);
    }

    // Indica si el movimiento resta stock.
    public function esSalida(): bool
    {
        return in_array($this->tipoMovimiento, [
            self::TIPO_SALIDA_OBRA,
            self::TIPO_SALIDA_TIENDA,
            self::TIPO_AJUSTE_NEGATIVO,
        ], true);
    }

    // Devuelve la cantidad con signo matemático real.
    public function getCantidadConSigno(): float
    {
        $cantidad = (float) $this->cantidad;

        return $this->esSalida() ? -$cantidad : $cantidad;
    }
}