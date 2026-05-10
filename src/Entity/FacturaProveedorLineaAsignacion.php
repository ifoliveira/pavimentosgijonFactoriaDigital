<?php

namespace App\Entity;

use App\Repository\FacturaProveedorLineaAsignacionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ENTIDAD FACTURA PROVEEDOR LINEA ASIGNACION
 * ==========================================
 * Representa la asignación parcial o total de una línea de factura proveedor.
 *
 * Permite repartir una línea en varias partes:
 *
 * Ejemplo:
 * --------
 * Línea factura:
 *   Azulejo X → 10 cajas → 300 €
 *
 * Asignaciones:
 *   6 cajas → Proyecto A → 180 €
 *   4 cajas → Proyecto B → 120 €
 *
 * OBJETIVO:
 * ----------
 * - Evitar forzar una línea a un solo destino
 * - Permitir reparto realista de costes
 * - Base para generar ProyectoGasto
 *
 * RELACIONES:
 * ------------
 * - Pertenece a una FacturaProveedorLinea
 * - Puede estar vinculada a un Proyecto
 * - Puede generar un ProyectoGasto
 *
 * TIPOS DE DESTINO:
 * -----------------
 * obra     → genera ProyectoGasto
 * stock    → inventario / tienda
 * general  → gasto general
 * ignorar  → descartado
 *
 * ESTADO:
 * --------
 * pendiente → sin procesar
 * aplicada  → ya procesada
 */
#[ORM\Entity(repositoryClass: FacturaProveedorLineaAsignacionRepository::class)]
class FacturaProveedorLineaAsignacion
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // -------------------------------------------------------------------------
    // RELACIONES
    // -------------------------------------------------------------------------

    #[ORM\ManyToOne(inversedBy: 'asignaciones')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FacturaProveedorLinea $linea = null;

    #[ORM\ManyToOne]
    private ?Proyecto $proyecto = null;

    #[ORM\ManyToOne]
    private ?ProyectoGasto $proyectoGasto = null;

    // -------------------------------------------------------------------------
    // DATOS DE ASIGNACIÓN
    // -------------------------------------------------------------------------

    /**
     * Cantidad asignada de la línea
     */
    #[ORM\Column(type: 'float')]
    private float $cantidad = 0;

    /**
     * Importe asociado a esta asignación
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importe = '0.00';

    /**
     * Tipo de destino
     */
    #[ORM\Column(length: 50)]
    private string $tipoDestino = 'obra';

    /**
     * Estado de la asignación
     */
    #[ORM\Column(length: 50)]
    private string $estado = 'pendiente';

    public function __construct()
    {
        $this->asignaciones = new ArrayCollection();
    }

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLinea(): ?FacturaProveedorLinea
    {
        return $this->linea;
    }

    public function setLinea(?FacturaProveedorLinea $linea): self
    {
        $this->linea = $linea;
        return $this;
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

    public function getProyectoGasto(): ?ProyectoGasto
    {
        return $this->proyectoGasto;
    }

    public function setProyectoGasto(?ProyectoGasto $proyectoGasto): self
    {
        $this->proyectoGasto = $proyectoGasto;
        return $this;
    }

    public function getCantidad(): float
    {
        return $this->cantidad;
    }

    public function setCantidad(float $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function getImporte(): string
    {
        return $this->importe;
    }

    public function setImporte(string $importe): self
    {
        $this->importe = $importe;
        return $this;
    }

    public function getTipoDestino(): string
    {
        return $this->tipoDestino;
    }

    public function setTipoDestino(string $tipoDestino): self
    {
        $this->tipoDestino = $tipoDestino;
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
}