<?php

namespace App\Entity;

use App\Repository\ManoObraRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ENTIDAD MANOOBRA
 * =================
 * Describe los trabajos de mano de obra asociados a un documento.
 * Tiene DOS propósitos independientes:
 *
 * 1. DESCRIPTIVO (para el impreso del presupuesto):
 *    Cada registro representa una categoría de trabajo con su descripción
 *    narrativa. Se imprime en el presupuesto para que el cliente sepa
 *    exactamente qué trabajos se van a realizar.
 *
 *    Ejemplo impreso:
 *      Albañilería:
 *        Tirar paredes, azulejar suelo y paredes, retirar escombros
 *      Fontanería:
 *        Preparar tuberías, instalar desagüe ducha, conexión grifería
 *
 * 2. CONTROL DE COSTE INTERNO:
 *    El campo coste es una estimación interna de lo que nos va a costar
 *    ese trabajo. NO aparece en el impreso del cliente y NO tiene relación
 *    con los importes de DocumentoLinea.
 *    Sirve para controlar pagos pendientes a operarios propios.
 *
 * SEPARACIÓN DE RESPONSABILIDADES:
 * ----------------------------------
 * ManoObra    → descripción narrativa + coste estimado interno
 * CosteGremio → coste de empresas externas (fontanero autónomo, etc.)
 * DocumentoLinea (tipoLinea=mano_obra) → importe que se cobra al cliente
 *
 * RELACIONES:
 * -----------
 * Apunta a Documento (nuevo modelo).
 * Mantiene también relación con Presupuestos (modelo antiguo) para
 * no romper la funcionalidad existente durante la migración progresiva.
 *
 * CATEGORÍAS:
 * -----------
 * Las categorías son un catálogo fijo gestionado en TipoManoObra.
 * Ejemplos: Albañilería, Fontanería, Electricidad, Carpintería...
 */
#[ORM\Entity(repositoryClass: ManoObraRepository::class)]
#[ORM\Table(name: 'mano_obra')]
class ManoObra
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // -------------------------------------------------------------------------
    // RELACIONES CON DOCUMENTO
    // -------------------------------------------------------------------------

    /**
     * Documento (nuevo modelo) al que pertenece esta descripción de trabajo.
     * Puede ser presupuesto o factura.
     */
    #[ORM\ManyToOne(
        targetEntity: Documento::class,
        inversedBy: 'manoObra',
        fetch: 'EAGER',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?Documento $documentoMo = null;

    /**
     * Relación con el modelo antiguo Presupuestos.
     * Se mantiene durante la migración progresiva.
     * No usar en código nuevo.
     * @deprecated Usar documentoMo en su lugar
     */
    #[ORM\ManyToOne(
        targetEntity: Presupuestos::class,
        inversedBy: 'manoObra',
        fetch: 'EAGER',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?Presupuestos $presupuestoMo = null;

    // -------------------------------------------------------------------------
    // CATEGORÍA Y DESCRIPCIÓN
    // -------------------------------------------------------------------------

    /**
     * Categoría del trabajo. Catálogo fijo en TipoManoObra.
     * Ejemplos: Albañilería, Fontanería, Electricidad...
     */
    #[ORM\ManyToOne(
        targetEntity: TipoManoObra::class,
        inversedBy: 'manoObras'
    )]
    private ?TipoManoObra $categoriaMo = null;

    /**
     * Descripción narrativa de los trabajos a realizar.
     * Es lo que aparece impreso en el presupuesto bajo la categoría.
     * Ejemplo: "Tirar paredes, azulejar suelo y paredes, retirar escombros"
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textoMo = null;

    // -------------------------------------------------------------------------
    // COSTE INTERNO
    // -------------------------------------------------------------------------

    /**
     * Coste estimado interno de este trabajo.
     * NO aparece en el impreso del cliente.
     * NO está relacionado con DocumentoLinea.costeUnitario.
     * Sirve para controlar pagos pendientes a operarios propios.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $coste = null;

    /**
     * Indica si este coste ya ha sido pagado al operario.
     * Permite llevar un control de pagos pendientes internos.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $pagado = false;

    // -------------------------------------------------------------------------
    // PAGO AL OPERARIO
    // -------------------------------------------------------------------------

    /**
     * Método de pago usado cuando pagado = true.
     * Valores: efectivo | transferencia | bizum
     * Null mientras no se haya pagado.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $metodoPago = null;

    /**
     * Fecha en que se realizó el pago al operario.
     * Null mientras no se haya pagado.
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaPago = null;

    /**
     * Vinculación con movimiento bancario.
     * Solo se rellena cuando metodoPago = transferencia o bizum.
     * Null si el pago fue en efectivo.
     */
    #[ORM\ManyToOne(targetEntity: Banco::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Banco $banco = null;
    //-------------------------------------------------------------------------
    // RELACIÓN CON TEXTOS SELECCIONADOS (CATÁLOGO)
    //-------------------------------------------------------------------------

    /**
     * Textos seleccionados de catálogo para esta categoría de mano de obra.
     * Permite construir la descripción narrativa a partir de textos predefinidos.
     * El orden se controla mediante el campo "orden" en ManoObraTextoSeleccionado.
     */
    #[ORM\OneToMany(
        mappedBy: 'manoObra',
        targetEntity: ManoObraTextoSeleccionado::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['orden' => 'ASC', 'id' => 'ASC'])]
    private Collection $seleccionesTexto;    
    // -------------------------------------------------------------------------
    // CONSTRUCTOR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->pagado = false;
        $this->seleccionesTexto = new ArrayCollection();
    }

    // -------------------------------------------------------------------------
    // GETTERS Y SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentoMo(): ?Documento
    {
        return $this->documentoMo;
    }

    public function setDocumentoMo(?Documento $documentoMo): static
    {
        $this->documentoMo = $documentoMo;
        return $this;
    }

    public function getPresupuestoMo(): ?Presupuestos
    {
        return $this->presupuestoMo;
    }

    public function setPresupuestoMo(?Presupuestos $presupuestoMo): static
    {
        $this->presupuestoMo = $presupuestoMo;
        return $this;
    }

    public function getCategoriaMo(): ?TipoManoObra
    {
        return $this->categoriaMo;
    }

    public function setCategoriaMo(?TipoManoObra $categoriaMo): static
    {
        $this->categoriaMo = $categoriaMo;
        return $this;
    }

    public function getTextoMo(): ?string
    {
        return $this->textoMo;
    }

    public function setTextoMo(?string $textoMo): static
    {
        $this->textoMo = $textoMo;
        return $this;
    }

    public function getCoste(): ?string
    {
        return $this->coste;
    }

    public function setCoste(?string $coste): static
    {
        $this->coste = $coste;
        return $this;
    }

    public function isPagado(): bool
    {
        return $this->pagado;
    }

    public function setPagado(bool $pagado): static
    {
        $this->pagado = $pagado;
        return $this;
    }

    public function getMetodoPago(): ?string
    {
        return $this->metodoPago;
    }

    public function getFechaPago(): ?\DateTimeInterface
    {
        return $this->fechaPago;
    }

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setMetodoPago(?string $metodoPago): static
    {
        $this->metodoPago = $metodoPago;
        return $this;
    }

    public function setFechaPago(?\DateTimeInterface $fechaPago): static
    {
        $this->fechaPago = $fechaPago;
        return $this;
    }

    public function setBanco(?Banco $banco): static
    {
        $this->banco = $banco;
        return $this;
    }    

    public function getSeleccionesTexto(): Collection
    {
        return $this->seleccionesTexto;
    }

    public function addSeleccionTexto(ManoObraTextoSeleccionado $seleccion): self
    {
        if (!$this->seleccionesTexto->contains($seleccion)) {
            $this->seleccionesTexto[] = $seleccion;
            $seleccion->setManoObra($this);
        }

        return $this;
    }

    public function removeSeleccionTexto(ManoObraTextoSeleccionado $seleccion): self
    {
        if ($this->seleccionesTexto->removeElement($seleccion)) {
            if ($seleccion->getManoObra() === $this) {
                $seleccion->setManoObra(null);
            }
        }

        return $this;
    }

    public function clearSeleccionesTexto(): self
    {
        foreach ($this->seleccionesTexto as $seleccion) {
            $this->removeSeleccionTexto($seleccion);
        }

        return $this;
    }    


    public function setPago(
        bool $pagado,
        ?string $metodoPago = null,
        ?\DateTimeInterface $fechaPago = null,
        ?Banco $banco = null
    ): static {
        $this->pagado = $pagado;
        $this->metodoPago = $metodoPago;
        $this->fechaPago = $fechaPago;
        $this->banco = $banco;
        return $this;
    }

    public function getLineasRender(): array
    {
        $lineas = [];

        // ─── textos seleccionados ───
        foreach ($this->getSeleccionesTexto() as $sel) {
            if ($sel->getTextoManoObra()) {
                $texto = trim((string) $sel->getTextoManoObra()->getDescripcionXo());
                if ($texto !== '') {
                    $lineas[] = $texto;
                }
            }
        }

        // ─── texto manual adicional ───
        if ($this->getTextoMo()) {
            foreach (explode(';', $this->getTextoMo()) as $linea) {
                $linea = trim($linea);
                if ($linea !== '') {
                    $lineas[] = $linea;
                }
            }
        }

        return $lineas;
    }        
}