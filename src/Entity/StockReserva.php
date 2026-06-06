<?php

namespace App\Entity;

use App\Repository\StockReservaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockReservaRepository::class)]
class StockReserva
{
    public const ESTADO_RESERVADA = 'reservada';
    public const ESTADO_CONSUMIDA = 'consumida';
    public const ESTADO_LIBERADA = 'liberada';
    public const ESTADO_CANCELADA = 'cancelada';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Producto interno asociado a la reserva.
    // Es nullable porque puede haber stock sin producto normalizado.
    #[ORM\ManyToOne(targetEntity: Productos::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Productos $producto = null;

    // Movimiento de entrada concreto del que viene el stock, si quieres trazabilidad fina.
    // Puede ser null si reservas por producto de forma genérica.
    #[ORM\ManyToOne(targetEntity: StockMovimiento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StockMovimiento $stockMovimientoEntrada = null;

    // Documento/presupuesto donde se ha reservado este stock.
    #[ORM\ManyToOne(targetEntity: Documento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Documento $documento = null;

    // Línea concreta del presupuesto que usa esta reserva.
    #[ORM\OneToOne(targetEntity: DocumentoLinea::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?DocumentoLinea $documentoLinea = null;

    // Proyecto asociado si el presupuesto ya está vinculado a obra.
    #[ORM\ManyToOne(targetEntity: Proyecto::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Proyecto $proyecto = null;

    // Descripción congelada en el momento de reservar.
    #[ORM\Column(type: Types::TEXT)]
    private ?string $descripcionProducto = null;

    // Referencia del proveedor si existe.
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenciaProveedor = null;

    // Cantidad reservada.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $cantidad = '0.000';

    // Coste unitario estimado de la reserva.
    // Útil para margen del presupuesto/proyecto.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $costeUnitario = null;

    // Estado: reservada, consumida, liberada, cancelada.
    #[ORM\Column(length: 30)]
    private string $estado = self::ESTADO_RESERVADA;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaReserva = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaCaducidad = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaResolucion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    public function __construct()
    {
        $this->fechaReserva = new \DateTime();
    }

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

    public function getStockMovimientoEntrada(): ?StockMovimiento
    {
        return $this->stockMovimientoEntrada;
    }

    public function setStockMovimientoEntrada(?StockMovimiento $stockMovimientoEntrada): static
    {
        $this->stockMovimientoEntrada = $stockMovimientoEntrada;
        return $this;
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

    public function getDocumentoLinea(): ?DocumentoLinea
    {
        return $this->documentoLinea;
    }

    public function setDocumentoLinea(?DocumentoLinea $documentoLinea): static
    {
        $this->documentoLinea = $documentoLinea;
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

    public function getCantidad(): string
    {
        return $this->cantidad;
    }

    public function setCantidad(string|float|int $cantidad): static
    {
        $this->cantidad = number_format((float) $cantidad, 3, '.', '');
        return $this;
    }

    public function getCosteUnitario(): ?string
    {
        return $this->costeUnitario;
    }

    public function setCosteUnitario(string|float|int|null $costeUnitario): static
    {
        $this->costeUnitario = $costeUnitario !== null
            ? number_format((float) $costeUnitario, 2, '.', '')
            : null;

        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;
        return $this;
    }

    public function getFechaReserva(): ?\DateTimeInterface
    {
        return $this->fechaReserva;
    }

    public function setFechaReserva(\DateTimeInterface $fechaReserva): static
    {
        $this->fechaReserva = $fechaReserva;
        return $this;
    }

    public function getFechaCaducidad(): ?\DateTimeInterface
    {
        return $this->fechaCaducidad;
    }

    public function setFechaCaducidad(?\DateTimeInterface $fechaCaducidad): static
    {
        $this->fechaCaducidad = $fechaCaducidad;
        return $this;
    }

    public function getFechaResolucion(): ?\DateTimeInterface
    {
        return $this->fechaResolucion;
    }

    public function setFechaResolucion(?\DateTimeInterface $fechaResolucion): static
    {
        $this->fechaResolucion = $fechaResolucion;
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

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_RESERVADA;
    }

    public function consumir(): static
    {
        $this->estado = self::ESTADO_CONSUMIDA;
        $this->fechaResolucion = new \DateTime();

        return $this;
    }

    public function liberar(): static
    {
        $this->estado = self::ESTADO_LIBERADA;
        $this->fechaResolucion = new \DateTime();

        return $this;
    }

    public function cancelar(): static
    {
        $this->estado = self::ESTADO_CANCELADA;
        $this->fechaResolucion = new \DateTime();

        return $this;
    }
}