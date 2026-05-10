<?php

namespace App\Entity;

use App\Repository\CatalogoProductoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CatalogoProductoRepository::class)]
#[ORM\Table(name: 'catalogo_producto')]
class CatalogoProducto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Código interno opcional.
     * Ejemplo: PLATO-RES-17070, MAMP-FRONTAL-STD.
     */
    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $codigo = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    /**
     * Familia principal:
     * plato_ducha, mampara, griferia, mueble_bano, sanitario,
     * azulejo, pavimento, material_agarre, auxiliar...
     */
    #[ORM\Column(type: 'string', length: 80)]
    private ?string $familia = null;

    /**
     * Subfamilia:
     * resina, frontal_fijo_corredera, angular, suspendido_2_cajones...
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $subfamilia = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $marca = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $modelo = null;

    /**
     * ud, m2, ml, caja, saco...
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $unidad = 'ud';

    /**
     * Precios congelables luego en DocumentoLinea.
     * Doctrine decimal devuelve string.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $precioVenta = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $precioCoste = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $tipoIva = '21.00';

    /**
     * Medidas opcionales en cm.
     * No todos los productos tendrán todas.
     */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $ancho = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $alto = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $largo = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $fondo = null;

    /**
     * Texto libre para medidas complejas.
     * Ejemplo: "170x70", "80 cm 2 cajones", "30x60 rectificado".
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $medidaTexto = null;

    /**
     * Campo flexible para datos del proveedor, acabados, color, etc.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $atributos = null;

    /**
     * Preparado para stock futuro.
     * Al principio puedes dejar controlaStock=false en casi todo.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $controlaStock = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $stockActual = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $stockMinimo = '0.00';

    /**
     * Si está activo se puede usar.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $activo = true;

    /**
     * Si aparece en buscadores/selectores de presupuesto.
     * Puede haber productos de stock que no quieras presupuestar directamente.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $visiblePresupuesto = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(
        mappedBy: 'producto',
        targetEntity: CatalogoProductoConfiguracion::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $configuraciones;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->configuraciones = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): static
    {
        $this->codigo = $codigo;
        $this->touch();

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        $this->touch();

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        $this->touch();

        return $this;
    }

    public function getFamilia(): ?string
    {
        return $this->familia;
    }

    public function setFamilia(string $familia): static
    {
        $this->familia = $familia;
        $this->touch();

        return $this;
    }

    public function getSubfamilia(): ?string
    {
        return $this->subfamilia;
    }

    public function setSubfamilia(?string $subfamilia): static
    {
        $this->subfamilia = $subfamilia;
        $this->touch();

        return $this;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function setMarca(?string $marca): static
    {
        $this->marca = $marca;
        $this->touch();

        return $this;
    }

    public function getModelo(): ?string
    {
        return $this->modelo;
    }

    public function setModelo(?string $modelo): static
    {
        $this->modelo = $modelo;
        $this->touch();

        return $this;
    }

    public function getUnidad(): string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = $unidad;
        $this->touch();

        return $this;
    }

    public function getPrecioVenta(): string
    {
        return $this->precioVenta;
    }

    public function setPrecioVenta(string|float|int $precioVenta): static
    {
        $this->precioVenta = number_format((float) $precioVenta, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getPrecioCoste(): string
    {
        return $this->precioCoste;
    }

    public function setPrecioCoste(string|float|int $precioCoste): static
    {
        $this->precioCoste = number_format((float) $precioCoste, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getTipoIva(): string
    {
        return $this->tipoIva;
    }

    public function setTipoIva(string|float|int $tipoIva): static
    {
        $this->tipoIva = number_format((float) $tipoIva, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getAncho(): ?string
    {
        return $this->ancho;
    }

    public function setAncho(string|float|int|null $ancho): static
    {
        $this->ancho = $ancho !== null && $ancho !== ''
            ? number_format((float) $ancho, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getAlto(): ?string
    {
        return $this->alto;
    }

    public function setAlto(string|float|int|null $alto): static
    {
        $this->alto = $alto !== null && $alto !== ''
            ? number_format((float) $alto, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getLargo(): ?string
    {
        return $this->largo;
    }

    public function setLargo(string|float|int|null $largo): static
    {
        $this->largo = $largo !== null && $largo !== ''
            ? number_format((float) $largo, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getFondo(): ?string
    {
        return $this->fondo;
    }

    public function setFondo(string|float|int|null $fondo): static
    {
        $this->fondo = $fondo !== null && $fondo !== ''
            ? number_format((float) $fondo, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getMedidaTexto(): ?string
    {
        return $this->medidaTexto;
    }

    public function setMedidaTexto(?string $medidaTexto): static
    {
        $this->medidaTexto = $medidaTexto;
        $this->touch();

        return $this;
    }

    public function getAtributos(): ?array
    {
        return $this->atributos;
    }

    public function setAtributos(?array $atributos): static
    {
        $this->atributos = $atributos;
        $this->touch();

        return $this;
    }

    public function getAtributo(string $clave, mixed $default = null): mixed
    {
        return $this->atributos[$clave] ?? $default;
    }

    public function setAtributo(string $clave, mixed $valor): static
    {
        $atributos = $this->atributos ?? [];
        $atributos[$clave] = $valor;
        $this->atributos = $atributos;
        $this->touch();

        return $this;
    }

    public function isControlaStock(): bool
    {
        return $this->controlaStock;
    }

    public function setControlaStock(bool $controlaStock): static
    {
        $this->controlaStock = $controlaStock;
        $this->touch();

        return $this;
    }

    public function getStockActual(): string
    {
        return $this->stockActual;
    }

    public function setStockActual(string|float|int $stockActual): static
    {
        $this->stockActual = number_format((float) $stockActual, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getStockMinimo(): string
    {
        return $this->stockMinimo;
    }

    public function setStockMinimo(string|float|int $stockMinimo): static
    {
        $this->stockMinimo = number_format((float) $stockMinimo, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;
        $this->touch();

        return $this;
    }

    public function isVisiblePresupuesto(): bool
    {
        return $this->visiblePresupuesto;
    }

    public function setVisiblePresupuesto(bool $visiblePresupuesto): static
    {
        $this->visiblePresupuesto = $visiblePresupuesto;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, CatalogoProductoConfiguracion>
     */
    public function getConfiguraciones(): Collection
    {
        return $this->configuraciones;
    }

    public function addConfiguracion(CatalogoProductoConfiguracion $configuracion): static
    {
        if (!$this->configuraciones->contains($configuracion)) {
            $this->configuraciones->add($configuracion);
            $configuracion->setProducto($this);
        }

        return $this;
    }

    public function removeConfiguracion(CatalogoProductoConfiguracion $configuracion): static
    {
        if ($this->configuraciones->removeElement($configuracion)) {
            if ($configuracion->getProducto() === $this) {
                $configuracion->setProducto(null);
            }
        }

        return $this;
    }
}