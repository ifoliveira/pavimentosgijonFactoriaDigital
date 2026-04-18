<?php

namespace App\Entity;

use App\Repository\DocumentoCobroRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD DOCUMENTOCOBRO
 * ======================
 * Representa cada pago parcial o total que realiza un cliente
 * sobre un Documento (factura o ticket).
 *
 * Un documento puede tener varios cobros (pagos parciales).
 * La suma de todos los cobros se refleja en Documento.totalCobrado.
 * Cuando totalCobrado >= Documento.total, el DocumentoService
 * actualiza estadoCobro = 'cobrado' automáticamente.
 *
 * MÉTODOS DE PAGO:
 * ----------------
 * - efectivo:      Pago en mano. No genera movimiento en Banco.
 * - tarjeta:       Pago con datáfono. Puede tener recargo por comisión bancaria.
 * - transferencia: Ingreso directo en cuenta. Se vincula a registro Banco.
 * - bizum:         Pago móvil. Se vincula a registro Banco.
 * - financiacion:  El cliente financia el pago a través de empresa externa.
 *                  Nosotros cobramos de la financiera (puede tener comisión).
 *
 * RECARGOS Y COMISIONES:
 * ----------------------
 * Tarjeta y financiación suelen tener un coste para el negocio:
 * el banco o la financiera se queda un porcentaje.
 *
 * Ejemplo real:
 *   Cliente paga 1.000€ con tarjeta
 *   Comisión banco: 1.5% = 15€
 *   Nosotros recibimos realmente: 985€
 *
 * Campos para reflejarlo:
 *   importeBruto     = 1.000€  (lo que paga el cliente)
 *   porcentajeRecargo = 1.50
 *   importeRecargo   = 15.00€  (calculado por DocumentoService)
 *   importeNeto      = 985.00€ (lo que realmente ingresamos)
 *
 * En Documento.totalCobrado se suma importeBruto (lo que debe el cliente)
 * pero el recargo queda registrado para calcular rentabilidad real.
 *
 * VINCULACIÓN CON BANCO:
 * ----------------------
 * Cuando el cobro genera un movimiento real en cuenta bancaria
 * (transferencia, bizum, tarjeta, financiación), se puede vincular
 * al registro Banco correspondiente para facilitar la conciliación.
 * Efectivo no genera registro en Banco.
 *
 * REFERENCIA EXTERNA:
 * -------------------
 * Campo libre para anotar: número de transferencia, últimos dígitos
 * de tarjeta, referencia de la financiera, etc.
 */
#[ORM\Entity(repositoryClass: DocumentoCobroRepository::class)]
#[ORM\Table(name: 'documento_cobro')]
class DocumentoCobro
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Documento (factura o ticket) al que pertenece este cobro.
     */
    #[ORM\ManyToOne(targetEntity: Documento::class, inversedBy: 'cobros')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Documento $documento = null;

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
     * Es el que se suma a Documento.totalCobrado.
     * Es lo que el cliente debe y ha pagado, independientemente
     * de lo que nos cueste el método de pago.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeBruto = '0.00';

    /**
     * Porcentaje de comisión del banco o financiera.
     * Ejemplo: 1.50 para un 1,5% de comisión en tarjeta.
     * 0.00 si no hay recargo (efectivo, bizum sin comisión...).
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $porcentajeRecargo = '0.00';

    /**
     * Importe del recargo calculado.
     * importeRecargo = importeBruto × (porcentajeRecargo / 100)
     * Lo calcula DocumentoService, aquí solo se almacena.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeRecargo = '0.00';

    /**
     * Importe que realmente ingresamos después del recargo.
     * importeNeto = importeBruto - importeRecargo
     * Útil para saber la rentabilidad real de cada cobro.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $importeNeto = '0.00';

    // -------------------------------------------------------------------------
    // TRAZABILIDAD
    // -------------------------------------------------------------------------

    /**
     * Referencia externa del cobro. Campo libre.
     * Ejemplos:
     *   transferencia → número de operación del banco
     *   tarjeta       → "VISA *1234"
     *   financiacion  → referencia del contrato de financiación
     *   bizum         → número de teléfono origen
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $referencia = null;

    /**
     * Vinculación con el registro de movimiento bancario.
     * Se rellena cuando el cobro ya aparece en el extracto bancario
     * y se ha conciliado con un registro de la entidad Banco.
     * Efectivo nunca tendrá este campo relleno.
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

    public function getDocumento(): ?Documento
    {
        return $this->documento;
    }

    public function setDocumento(?Documento $documento): static
    {
        $this->documento = $documento;
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