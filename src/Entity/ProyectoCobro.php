<?php

namespace App\Entity;

use App\Repository\ProyectoCobroRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD PROYECTOCOBRO
 * =====================
 * Representa cada pago parcial o total que realiza un cliente
 * sobre un Proyecto.
 *
 * Un proyecto puede tener varios cobros.
 * La suma de todos los cobros se reflejará en Proyecto.totalCobrado
 * o se calculará mediante ProyectoService/ProyectoCalcularService.
 *
 * IDEA DE USO:
 * ------------
 * El Proyecto es la operación real con el cliente.
 *
 * Puede tener:
 * - Presupuesto inicial
 * - Presupuestos adicionales
 * - Factura de obra
 * - Ticket de tienda
 * - Cobros globales del cliente
 *
 * Por eso el cobro se asocia al Proyecto, no necesariamente
 * a una factura o ticket concreto.
 *
 * MÉTODOS DE PAGO:
 * ----------------
 * - efectivo:      Pago en mano. No genera movimiento en Banco.
 * - tarjeta:       Pago con datáfono. Puede tener comisión bancaria.
 * - transferencia: Ingreso directo en cuenta. Se vincula a registro Banco.
 * - bizum:         Pago móvil. Se vincula a registro Banco.
 * - financiacion:  El cliente financia el pago a través de empresa externa.
 *
 * RECARGOS Y COMISIONES:
 * ----------------------
 * Ejemplo:
 *   Cliente paga 1.000€ con tarjeta
 *   Comisión banco: 1.5% = 15€
 *   Nosotros recibimos realmente: 985€
 *
 * Campos:
 *   importeBruto      = 1.000€  lo que paga el cliente
 *   porcentajeRecargo = 1.50
 *   importeRecargo    = 15.00€
 *   importeNeto       = 985.00€
 *
 * En el total cobrado del Proyecto se suma importeBruto.
 * El importeNeto sirve para rentabilidad/coste financiero real.
 */
#[ORM\Entity(repositoryClass: ProyectoCobroRepository::class)]
#[ORM\Table(name: 'proyecto_cobro')]
class ProyectoCobro
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Proyecto al que pertenece este cobro.
     */
    #[ORM\ManyToOne(targetEntity: Proyecto::class, inversedBy: 'cobros')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proyecto $proyecto = null;

    // -------------------------------------------------------------------------
    // FECHA Y MÉTODO
    // -------------------------------------------------------------------------

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fecha = null;

    /**
     * Método de pago utilizado.
     * Valores: efectivo | tarjeta | transferencia | bizum | financiacion
     */
    #[ORM\Column(type: 'string', length: 20)]
    private ?string $metodo = null;

    // -------------------------------------------------------------------------
    // IMPORTES
    // -------------------------------------------------------------------------

    /**
     * Importe que paga el cliente.
     * Es lo que reduce la deuda del proyecto.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeBruto = '0.00';

    /**
     * Porcentaje de comisión del banco o financiera.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $porcentajeRecargo = '0.00';

    /**
     * Importe del recargo/comisión.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeRecargo = '0.00';

    /**
     * Importe realmente recibido después de comisiones.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeNeto = '0.00';

    // -------------------------------------------------------------------------
    // TRAZABILIDAD
    // -------------------------------------------------------------------------

    /**
     * Referencia externa del cobro.
     * Ejemplos:
     * - Nº transferencia
     * - VISA *1234
     * - Referencia financiación
     * - Teléfono Bizum
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $referencia = null;

    /**
     * Movimiento bancario conciliado.
     */
    #[ORM\ManyToOne(targetEntity: Banco::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Banco $banco = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $creadoEn = null;

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

    public function getProyecto(): ?Proyecto
    {
        return $this->proyecto;
    }

    public function setProyecto(?Proyecto $proyecto): static
    {
        $this->proyecto = $proyecto;

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

    public function getMetodo(): ?string
    {
        return $this->metodo;
    }

    public function setMetodo(string $metodo): static
    {
        $this->metodo = $metodo;

        return $this;
    }

    public function getImporteBruto(): string
    {
        return $this->importeBruto;
    }

    public function setImporteBruto(string $importeBruto): static
    {
        $this->importeBruto = $importeBruto;

        return $this;
    }

    public function getPorcentajeRecargo(): string
    {
        return $this->porcentajeRecargo;
    }

    public function setPorcentajeRecargo(string $porcentajeRecargo): static
    {
        $this->porcentajeRecargo = $porcentajeRecargo;

        return $this;
    }

    public function getImporteRecargo(): string
    {
        return $this->importeRecargo;
    }

    public function setImporteRecargo(string $importeRecargo): static
    {
        $this->importeRecargo = $importeRecargo;

        return $this;
    }

    public function getImporteNeto(): string
    {
        return $this->importeNeto;
    }

    public function setImporteNeto(string $importeNeto): static
    {
        $this->importeNeto = $importeNeto;

        return $this;
    }

    public function getReferencia(): ?string
    {
        return $this->referencia;
    }

    public function setReferencia(?string $referencia): static
    {
        $this->referencia = $referencia;

        return $this;
    }

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setBanco(?Banco $banco): static
    {
        $this->banco = $banco;

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
}