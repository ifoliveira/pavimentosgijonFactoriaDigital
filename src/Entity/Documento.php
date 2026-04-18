<?php

namespace App\Entity;

use App\Repository\DocumentoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD DOCUMENTO
 * =================
 * Representa cualquier documento comercial del negocio.
 * Un único modelo flexible que cubre tres tipos de documentos:
 *
 * - PRESUPUESTO: Oferta enviada al cliente antes de la obra.
 *               Puede ser inicial o adicional (modificado durante la obra).
 *               Requiere aprobación del cliente antes de ejecutarse.
 *               Cuando se acepta, genera o modifica una Factura.
 *
 * - FACTURA:    Documento fiscal y legal de la operación.
 *               Se construye a partir de un presupuesto aceptado.
 *               Admite cobros parciales y puede recibir líneas nuevas
 *               desde presupuestos adicionales aprobados.
 *               Solo se cierra cuando está completamente cobrada.
 *
 * - TICKET:     Venta directa en tienda sin presupuesto previo.
 *               Más simple: se cobra en el momento, sin seguimiento de obra.
 *
 * ESTADOS:
 * --------
 * Cada documento tiene TRES estados independientes que evolucionan por separado:
 *
 *   estadoComercial → ciclo de vida del documento con el cliente
 *      borrador → enviado → aceptado → rechazado → convertido → aplicado
 *      "convertido" = presupuesto inicial que ya tiene factura generada
 *      "aplicado"   = presupuesto adicional ya volcado en la factura
 *
 *   estadoCobro → situación del pago (solo relevante en facturas y tickets)
 *      pendiente → parcial → cobrado → devuelto
 *
 *   estadoEjecucion → situación de la obra (solo relevante en facturas de obra)
 *      no_aplica → pendiente → en_curso → completada → pausada
 *
 * RELACIONES ENTRE DOCUMENTOS:
 * -----------------------------
 * Un Documento puede apuntar a otro mediante dos campos:
 *
 *   facturaVinculada → usado en presupuestos adicionales para saber
 *                      a qué factura deben aplicarse cuando se acepten
 *
 *   presupuestosVinculados → colección inversa: desde una factura
 *                             puedes ver todos sus adicionales
 *
 * FLUJO TÍPICO DE UNA OBRA:
 * --------------------------
 * 1. Se crea Presupuesto inicial (borrador → enviado → aceptado → convertido)
 * 2. Se genera Factura a partir del presupuesto (pendiente de cobro)
 * 3. Cliente paga señal → estadoCobro = parcial
 * 4. Durante la obra surgen cambios:
 *    - Se crea Presupuesto adicional vinculado a la factura
 *    - Cliente lo aprueba → estadoComercial = aceptado
 *    - Sus líneas se vuelcan en la factura → estadoComercial = aplicado
 * 5. Se añaden cobros hasta completar el total
 * 6. estadoCobro = cobrado → factura cerrada, no editable
 *
 * FLUJO TÍPICO DE VENTA EN TIENDA:
 * ----------------------------------
 * 1. Se crea Ticket directamente
 * 2. Se añaden líneas de productos
 * 3. Se cobra en el momento → estadoCobro = cobrado
 * 4. estadoEjecucion = no_aplica (no hay obra)
 */
#[ORM\Entity(repositoryClass: DocumentoRepository::class)]
#[ORM\Table(name: 'documento')]
#[ORM\HasLifecycleCallbacks]
class Documento
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Prefijo del documento según tipo y año.
     * Ejemplos: P2025 (presupuesto), F2025 (factura), T2025 (ticket)
     */
    #[ORM\Column(type: 'string', length: 10)]
    private ?string $serie = null;

    /**
     * Número correlativo dentro de la serie.
     * La combinación serie+numero es única y no reutilizable.
     * Ejemplo: F2025-0042
     */
    #[ORM\Column(type: 'integer')]
    private ?int $numero = null;

    /**
     * Tipo de documento.
     * Valores: presupuesto | factura | ticket
     */
    #[ORM\Column(type: 'string', length: 20)]
    private ?string $tipoDocumento = null;

    // -------------------------------------------------------------------------
    // ESTADOS (independientes entre sí)
    // -------------------------------------------------------------------------

    /**
     * Estado comercial del documento con el cliente.
     * Valores: borrador | enviado | aceptado | rechazado | convertido | aplicado
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $estadoComercial = 'borrador';

    /**
     * Estado del cobro. Solo relevante en facturas y tickets.
     * Valores: pendiente | parcial | cobrado | devuelto
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $estadoCobro = 'pendiente';

    /**
     * Estado de ejecución de la obra. Solo relevante en facturas de obra.
     * Valores: no_aplica | pendiente | en_curso | completada | pausada
     */
    #[ORM\Column(type: 'string', length: 20)]
    private string $estadoEjecucion = 'no_aplica';

    // -------------------------------------------------------------------------
    // FECHAS
    // -------------------------------------------------------------------------

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fechaEmision = null;

    /**
     * Fecha límite de validez. Relevante en presupuestos.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaVencimiento = null;

    /**
     * Fecha en que el cliente aceptó el documento.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaAceptacion = null;

    // -------------------------------------------------------------------------
    // RELACIONES PRINCIPALES
    // -------------------------------------------------------------------------

    /**
     * Cliente al que pertenece este documento.
     * Apunta a la entidad Clientes existente (no se modifica).
     */
    #[ORM\ManyToOne(targetEntity: Clientes::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Clientes $cliente = null;

    /**
     * Proyecto (obra) al que pertenece este documento.
     * Nullable: los tickets de tienda no tienen proyecto.
     */
    #[ORM\ManyToOne(targetEntity: Proyecto::class, inversedBy: 'documentos')]
    private ?Proyecto $proyecto = null;

    /**
     * Colección de líneas de mano de obra asociadas a este documento.
     */
    #[ORM\OneToMany(mappedBy: 'documentoMo', targetEntity: ManoObra::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['documentoMo' => 'ASC', 'categoriaMo' => 'ASC'])]
    private Collection  $manoObra;    

    // -------------------------------------------------------------------------
    // RELACIONES ENTRE DOCUMENTOS
    // -------------------------------------------------------------------------

    /**
     * Solo se usa en presupuestos adicionales.
     * Apunta a la factura que debe modificarse cuando este presupuesto
     * adicional sea aceptado y aplicado.
     *
     * Ejemplo: presupuesto adicional P2025-0008 → factura F2025-0042
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'presupuestosVinculados')]
    private ?Documento $facturaVinculada = null;

    /**
     * Colección inversa de facturaVinculada.
     * Desde una factura, lista todos los presupuestos adicionales
     * que han sido o están pendientes de aplicarse.
     */
    #[ORM\OneToMany(mappedBy: 'facturaVinculada', targetEntity: self::class)]
    private Collection $presupuestosVinculados;

    // -------------------------------------------------------------------------
    // TOTALES (desnormalizados para rendimiento y consultas rápidas)
    // -------------------------------------------------------------------------

    /**
     * Base imponible: suma de subtotales de líneas sin IVA.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $baseImponible = '0.00';

    /**
     * Total de IVA acumulado de todas las líneas.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalIva = '0.00';

    /**
     * Total del documento: baseImponible + totalIva.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $total = '0.00';

    /**
     * Suma de todos los cobros registrados en DocumentoCobro.
     * Se actualiza cada vez que se añade un cobro.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalCobrado = '0.00';

    /**
     * Suma de costes reales de todas las líneas.
     * Permite calcular el margen bruto del documento.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalCoste = '0.00';

    // -------------------------------------------------------------------------
    // OTROS CAMPOS
    // -------------------------------------------------------------------------

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $creadoEn = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    // -------------------------------------------------------------------------
    // RELACIONES HIJO
    // -------------------------------------------------------------------------

    #[ORM\OneToMany(mappedBy: 'documento', targetEntity: DocumentoLinea::class,
        cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['posicion' => 'ASC'])]
    private Collection $lineas;

    #[ORM\OneToMany(mappedBy: 'documento', targetEntity: DocumentoCobro::class,
        cascade: ['persist', 'remove'])]
    private Collection $cobros;



    // -------------------------------------------------------------------------
    // CONSTRUCTOR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->presupuestosVinculados = new ArrayCollection();
        $this->lineas = new ArrayCollection();
        $this->cobros = new ArrayCollection();
        $this->fechaEmision = new \DateTime();
        $this->creadoEn = new \DateTime();
        $this->manoObra = new ArrayCollection();
    }

    // -------------------------------------------------------------------------
    // LIFECYCLE CALLBACKS
    // -------------------------------------------------------------------------

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

    public function getSerie(): ?string
    {
        return $this->serie;
    }

    public function setSerie(string $serie): static
    {
        $this->serie = $serie;
        return $this;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getNumeroFormateado(): string
    {
        return sprintf('%s-%04d', $this->serie, $this->numero);
    }

    public function getTipoDocumento(): ?string
    {
        return $this->tipoDocumento;
    }

    public function setTipoDocumento(string $tipoDocumento): static
    {
        $this->tipoDocumento = $tipoDocumento;
        return $this;
    }

    public function getEstadoComercial(): string
    {
        return $this->estadoComercial;
    }

    public function setEstadoComercial(string $estadoComercial): static
    {
        $this->estadoComercial = $estadoComercial;
        return $this;
    }

    public function getEstadoCobro(): string
    {
        return $this->estadoCobro;
    }

    public function setEstadoCobro(string $estadoCobro): static
    {
        $this->estadoCobro = $estadoCobro;
        return $this;
    }

    public function getEstadoEjecucion(): string
    {
        return $this->estadoEjecucion;
    }

    public function setEstadoEjecucion(string $estadoEjecucion): static
    {
        $this->estadoEjecucion = $estadoEjecucion;
        return $this;
    }

    public function getFechaEmision(): ?\DateTimeInterface
    {
        return $this->fechaEmision;
    }

    public function setFechaEmision(\DateTimeInterface $fechaEmision): static
    {
        $this->fechaEmision = $fechaEmision;
        return $this;
    }

    public function getFechaVencimiento(): ?\DateTimeInterface
    {
        return $this->fechaVencimiento;
    }

    public function setFechaVencimiento(?\DateTimeInterface $fechaVencimiento): static
    {
        $this->fechaVencimiento = $fechaVencimiento;
        return $this;
    }

    public function getFechaAceptacion(): ?\DateTimeInterface
    {
        return $this->fechaAceptacion;
    }

    public function setFechaAceptacion(?\DateTimeInterface $fechaAceptacion): static
    {
        $this->fechaAceptacion = $fechaAceptacion;
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

    public function getProyecto(): ?Proyecto
    {
        return $this->proyecto;
    }

    public function setProyecto(?Proyecto $proyecto): static
    {
        $this->proyecto = $proyecto;
        return $this;
    }

    public function getFacturaVinculada(): ?Documento
    {
        return $this->facturaVinculada;
    }

    public function setFacturaVinculada(?Documento $facturaVinculada): static
    {
        $this->facturaVinculada = $facturaVinculada;
        return $this;
    }

    public function getPresupuestosVinculados(): Collection
    {
        return $this->presupuestosVinculados;
    }

    public function getBaseImponible(): string
    {
        return $this->baseImponible;
    }

    public function setBaseImponible(string $baseImponible): static
    {
        $this->baseImponible = $baseImponible;
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

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
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

    public function getTotalCoste(): string
    {
        return $this->totalCoste;
    }

    public function setTotalCoste(string $totalCoste): static
    {
        $this->totalCoste = $totalCoste;
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

    public function getCreadoEn(): ?\DateTimeInterface
    {
        return $this->creadoEn;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }

    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(DocumentoLinea $linea): static
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setDocumento($this);
        }
        return $this;
    }

    public function removeLinea(DocumentoLinea $linea): static
    {
        $this->lineas->removeElement($linea);
        return $this;
    }

    public function getCobros(): Collection
    {
        return $this->cobros;
    }

    public function addCobro(DocumentoCobro $cobro): static
    {
        if (!$this->cobros->contains($cobro)) {
            $this->cobros->add($cobro);
            $cobro->setDocumento($this);
        }
        return $this;
    }

    public function removeCobro(DocumentoCobro $cobro): static
    {
        $this->cobros->removeElement($cobro);
        return $this;
    }

    public function getPendienteCobro(): string
    {
        return bcsub($this->total, $this->totalCobrado, 2);
    }

    /**
     * @return Collection|ManoObra[]
     */
    public function getManoObra(): Collection
    {
        return $this->manoObra;
    }

    public function addManoObra(ManoObra $manoObra): self
    {
        if (!$this->manoObra->contains($manoObra)) {
            $this->manoObra[] = $manoObra;
            $manoObra->setDocumentoMo($this);
        }
        return $this;
    }

    public function removeManoObra(ManoObra $manoObra): self
    {
        if ($this->manoObra->removeElement($manoObra)) {
            if ($manoObra->getDocumentoMo() === $this) {
                $manoObra->setDocumentoMo(null);
            }
        }
        return $this;
    }    
}