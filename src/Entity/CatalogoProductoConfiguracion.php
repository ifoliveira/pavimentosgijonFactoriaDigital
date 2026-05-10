<?php

namespace App\Entity;

use App\Repository\CatalogoProductoConfiguracionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CatalogoProductoConfiguracionRepository::class)]
#[ORM\Table(name: 'catalogo_producto_configuracion')]
class CatalogoProductoConfiguracion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CatalogoProducto::class, inversedBy: 'configuraciones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CatalogoProducto $producto = null;

    /**
     * Código del configurador donde puede usarse:
     * ducha, bano_completo, aseo...
     */
    #[ORM\Column(type: 'string', length: 50)]
    private ?string $configuradorCodigo = null;

    /**
     * Uso funcional dentro del configurador:
     * plato, mampara, griferia, mueble, sanitario, azulejo...
     */
    #[ORM\Column(type: 'string', length: 80)]
    private ?string $uso = null;

    /**
     * Tipo dentro del uso:
     * frontal_fijo_corredera, angular, resina, barra_estandar...
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $tipo = null;

    /**
     * Rango de compatibilidad en cm.
     * Ejemplo: mampara válida de ancho 120 a 180.
     */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $anchoMin = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $anchoMax = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $largoMin = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $largoMax = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $altoMin = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $altoMax = null;

    /**
     * Cuanto menor, más arriba aparece.
     */
    #[ORM\Column(type: 'integer')]
    private int $prioridad = 100;

    /**
     * Producto recomendado por defecto si encaja.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $recomendado = false;

    #[ORM\Column(type: 'boolean')]
    private bool $activo = true;

    /**
     * Condiciones adicionales flexibles.
     * Ejemplo: {"color": "blanco", "reversible": true, "instalacion": "entre_paredes"}
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $condiciones = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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
        $this->touch();

        return $this;
    }

    public function getConfiguradorCodigo(): ?string
    {
        return $this->configuradorCodigo;
    }

    public function setConfiguradorCodigo(string $configuradorCodigo): static
    {
        $this->configuradorCodigo = $configuradorCodigo;
        $this->touch();

        return $this;
    }

    public function getUso(): ?string
    {
        return $this->uso;
    }

    public function setUso(string $uso): static
    {
        $this->uso = $uso;
        $this->touch();

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): static
    {
        $this->tipo = $tipo;
        $this->touch();

        return $this;
    }

    public function getAnchoMin(): ?string
    {
        return $this->anchoMin;
    }

    public function setAnchoMin(string|float|int|null $anchoMin): static
    {
        $this->anchoMin = $anchoMin !== null && $anchoMin !== ''
            ? number_format((float) $anchoMin, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getAnchoMax(): ?string
    {
        return $this->anchoMax;
    }

    public function setAnchoMax(string|float|int|null $anchoMax): static
    {
        $this->anchoMax = $anchoMax !== null && $anchoMax !== ''
            ? number_format((float) $anchoMax, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getLargoMin(): ?string
    {
        return $this->largoMin;
    }

    public function setLargoMin(string|float|int|null $largoMin): static
    {
        $this->largoMin = $largoMin !== null && $largoMin !== ''
            ? number_format((float) $largoMin, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getLargoMax(): ?string
    {
        return $this->largoMax;
    }

    public function setLargoMax(string|float|int|null $largoMax): static
    {
        $this->largoMax = $largoMax !== null && $largoMax !== ''
            ? number_format((float) $largoMax, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getAltoMin(): ?string
    {
        return $this->altoMin;
    }

    public function setAltoMin(string|float|int|null $altoMin): static
    {
        $this->altoMin = $altoMin !== null && $altoMin !== ''
            ? number_format((float) $altoMin, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getAltoMax(): ?string
    {
        return $this->altoMax;
    }

    public function setAltoMax(string|float|int|null $altoMax): static
    {
        $this->altoMax = $altoMax !== null && $altoMax !== ''
            ? number_format((float) $altoMax, 2, '.', '')
            : null;

        $this->touch();

        return $this;
    }

    public function getPrioridad(): int
    {
        return $this->prioridad;
    }

    public function setPrioridad(int $prioridad): static
    {
        $this->prioridad = $prioridad;
        $this->touch();

        return $this;
    }

    public function isRecomendado(): bool
    {
        return $this->recomendado;
    }

    public function setRecomendado(bool $recomendado): static
    {
        $this->recomendado = $recomendado;
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

    public function getCondiciones(): ?array
    {
        return $this->condiciones;
    }

    public function setCondiciones(?array $condiciones): static
    {
        $this->condiciones = $condiciones;
        $this->touch();

        return $this;
    }

    public function getCondicion(string $clave, mixed $default = null): mixed
    {
        return $this->condiciones[$clave] ?? $default;
    }

    public function setCondicion(string $clave, mixed $valor): static
    {
        $condiciones = $this->condiciones ?? [];
        $condiciones[$clave] = $valor;
        $this->condiciones = $condiciones;
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
}