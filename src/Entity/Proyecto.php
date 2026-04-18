<?php

namespace App\Entity;

use App\Repository\ProyectoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD PROYECTO
 * =================
 * Agrupa todos los documentos relacionados con una obra concreta.
 * Es un contenedor organizativo, no un gestor de obra.
 *
 * No gestiona estados de ejecución, fases ni equipos.
 * Su único propósito es relacionar bajo un mismo paraguas:
 *   - Un presupuesto inicial
 *   - Sus presupuestos adicionales (si los hay)
 *   - Una factura final
 *
 * Esto permite al negocio tener una vista clara de cada obra:
 *   - ¿Qué se presupuestó originalmente?
 *   - ¿Qué cambios hubo durante la obra?
 *   - ¿Qué se facturó finalmente?
 *   - ¿Cuánto se cobró y cuánto queda pendiente?
 *
 * REGLAS DE NEGOCIO:
 * ------------------
 * - Siempre debe existir un presupuesto inicial antes de crear la factura.
 * - Solo puede haber una factura por proyecto.
 * - Los presupuestos adicionales son opcionales.
 * - Los tickets de tienda NO tienen proyecto (son ventas directas).
 *
 * KPIS DESNORMALIZADOS:
 * ---------------------
 * Se guardan los totales clave del proyecto para consultas rápidas
 * sin tener que recorrer todos los documentos vinculados.
 * Los actualiza ProyectoService cada vez que cambia un documento.
 *
 *   totalPresupuestado → importe del presupuesto inicial aceptado
 *   totalFacturado     → importe de la factura emitida
 *   totalCobrado       → suma de cobros registrados en la factura
 *
 * EJEMPLO REAL:
 * -------------
 * Proyecto: "Reforma baño completo - García - Calle Mayor 12"
 *   Cliente: García
 *   P2025-0007 (presupuesto inicial)   → 2.290€ → aceptado → convertido
 *   P2025-0008 (adicional: suelo rad.) → +880€  → aceptado → aplicado
 *   F2025-0042 (factura final)         → 3.170€ → cobro parcial 500€
 *
 *   totalPresupuestado = 2.290€
 *   totalFacturado     = 3.170€
 *   totalCobrado       = 500€
 *   pendiente          = 2.670€
 */
#[ORM\Entity(repositoryClass: ProyectoRepository::class)]
#[ORM\Table(name: 'proyecto')]
class Proyecto
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Nombre descriptivo del proyecto.
     * Debe ser suficientemente claro para identificar la obra de un vistazo.
     * Ejemplo: "Reforma baño completo - García - Calle Mayor 12"
     */
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nombre = null;

    /**
     * Cliente propietario del proyecto.
     * Apunta a la entidad Clientes existente (no se modifica).
     */
    #[ORM\ManyToOne(targetEntity: Clientes::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Clientes $cliente = null;

    // -------------------------------------------------------------------------
    // FECHAS
    // -------------------------------------------------------------------------

    /**
     * Fecha en que se crea el proyecto (inicio del proceso comercial).
     */
    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fechaInicio = null;

    /**
     * Fecha estimada de finalización de la obra.
     * Informativa, no vinculante.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaFinPrevista = null;

    /**
     * Fecha real en que se completó y cobró todo.
     * La rellena ProyectoService cuando estadoCobro de la factura = cobrado.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaFinReal = null;

    // -------------------------------------------------------------------------
    // NOTAS
    // -------------------------------------------------------------------------

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    // -------------------------------------------------------------------------
    // KPIS DESNORMALIZADOS
    // -------------------------------------------------------------------------

    /**
     * Importe total del presupuesto inicial aceptado (sin IVA).
     * Se actualiza cuando el presupuesto inicial cambia a estado 'convertido'.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalPresupuestado = '0.00';

    /**
     * Importe total de la factura emitida (sin IVA).
     * Puede diferir de totalPresupuestado si hubo adicionales.
     * Se actualiza cuando se recalcula la factura.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalFacturado = '0.00';

    /**
     * Suma de todos los cobros registrados en la factura del proyecto.
     * Se actualiza cada vez que se añade un DocumentoCobro.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalCobrado = '0.00';

    // -------------------------------------------------------------------------
    // RELACIONES CON DOCUMENTOS
    // -------------------------------------------------------------------------

    /**
     * Todos los documentos del proyecto (presupuestos y factura).
     * Se accede a ellos filtrando por tipoDocumento cuando sea necesario.
     */
    #[ORM\OneToMany(mappedBy: 'proyecto', targetEntity: Documento::class)]
    private Collection $documentos;

    // -------------------------------------------------------------------------
    // RELACIÓN CON GASTOS
    // ------------------------------------------------------------------------- 
     /* Se accede a ellos filtrando por documento o por categoría cuando sea necesario.
     * Pueden ser gastos previstos en el presupuesto inicial o adicionales,
     * gastos reflejados en la factura o gastos imprevistos sin documento asociado.
     * Ejemplos:
     * - gasto previsto en presupuesto inicial → categoría "materiales", "mano_obra_externa", etc.
     * - gasto reflejado en factura → categoría "factura"
     * - gasto imprevisto sin documento → categoría "imprevisto"
     */

    #[ORM\OneToMany(mappedBy: 'proyecto', targetEntity: ProyectoGasto::class, orphanRemoval: true)]
    private Collection $gastos;

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
        $this->documentos = new ArrayCollection();
        $this->fechaInicio = new \DateTime();
        $this->creadoEn = new \DateTime();
        $this->gastos = new ArrayCollection();

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

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getCliente(): ?Clientes
    {
        return $this->cliente;
    }

    public function setCliente(?Clientes $cliente): static
    {
        $this->cliente = $cliente;
        return $this;
    }

    public function getFechaInicio(): ?\DateTimeInterface
    {
        return $this->fechaInicio;
    }

    public function setFechaInicio(\DateTimeInterface $fechaInicio): static
    {
        $this->fechaInicio = $fechaInicio;
        return $this;
    }

    public function getFechaFinPrevista(): ?\DateTimeInterface
    {
        return $this->fechaFinPrevista;
    }

    public function setFechaFinPrevista(?\DateTimeInterface $fechaFinPrevista): static
    {
        $this->fechaFinPrevista = $fechaFinPrevista;
        return $this;
    }

    public function getFechaFinReal(): ?\DateTimeInterface
    {
        return $this->fechaFinReal;
    }

    public function setFechaFinReal(?\DateTimeInterface $fechaFinReal): static
    {
        $this->fechaFinReal = $fechaFinReal;
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

    public function getTotalPresupuestado(): string
    {
        return $this->totalPresupuestado;
    }

    public function setTotalPresupuestado(string $totalPresupuestado): static
    {
        $this->totalPresupuestado = $totalPresupuestado;
        return $this;
    }

    public function getTotalFacturado(): string
    {
        return $this->totalFacturado;
    }

    public function setTotalFacturado(string $totalFacturado): static
    {
        $this->totalFacturado = $totalFacturado;
        return $this;
    }

    public function getTotalCobrado(): string
    {
        return $this->totalCobrado;
    }

    public function setTotalCobrado(string $totalCobrado): static
    {
        $this->totalCobrado = $totalCobrado;
        return $this;
    }

    public function getDocumentos(): Collection
    {
        return $this->documentos;
    }

    public function addDocumento(Documento $documento): static
    {
        if (!$this->documentos->contains($documento)) {
            $this->documentos->add($documento);
            $documento->setProyecto($this);
        }
        return $this;
    }

    public function removeDocumento(Documento $documento): static
    {
        if ($this->documentos->removeElement($documento)) {
            if ($documento->getProyecto() === $this) {
                $documento->setProyecto(null);
            }
        }
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
     * Importe pendiente de cobro.
     * totalFacturado - totalCobrado (con IVA incluido en factura).
     */
    public function getPendienteCobro(): string
    {
        return bcsub($this->totalFacturado, $this->totalCobrado, 2);
    }

    /**
     * Desviación respecto al presupuesto inicial.
     * Positivo = la obra salió más cara de lo previsto.
     * Negativo = la obra salió más barata (poco habitual).
     */
    public function getDesviacion(): string
    {
        return bcsub($this->totalFacturado, $this->totalPresupuestado, 2);
    }

    public function getPendiente(): string
    {
        return number_format((float)$this->totalFacturado - (float)$this->totalCobrado, 2, '.', '');
    }    
    public function getGastos(): Collection
    {
        return $this->gastos;
    }

    public function addGasto(ProyectoGasto $gasto): self
    {
        if (!$this->gastos->contains($gasto)) {
            $this->gastos[] = $gasto;
            $gasto->setProyecto($this);
        }

        return $this;
    }

    public function removeGasto(ProyectoGasto $gasto): self
    {
        if ($this->gastos->removeElement($gasto)) {
            if ($gasto->getProyecto() === $this) {
                $gasto->setProyecto(null);
            }
        }

        return $this;
    }    
}
