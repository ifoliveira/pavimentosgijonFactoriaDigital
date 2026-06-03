<?php

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait FiscalTrait
{
    // Importe unitario final con IVA y recargo incluidos.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $precioCosteUnitario = null;

    // Importe unitario base sin impuestos.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $costeUnitarioBase = null;

    // Porcentaje de IVA aplicado.
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $porcentajeIva = null;

    // Importe unitario del IVA.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $importeIvaUnitario = null;

    // Indica si tiene recargo de equivalencia.
    #[ORM\Column(options: ['default' => false])]
    private bool $tieneRecargoEquivalencia = false;

    // Porcentaje del recargo de equivalencia.
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $porcentajeRecargoEquivalencia = null;

    // Importe unitario del recargo de equivalencia.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $importeRecargoUnitario = null;

    // =========================
    // GETTERS / SETTERS
    // =========================

    public function getPrecioCosteUnitario(): ?string
    {
        return $this->precioCosteUnitario;
    }

    public function setPrecioCosteUnitario(string|float|int|null $precioCosteUnitario): static
    {
        $this->precioCosteUnitario = $precioCosteUnitario !== null
            ? (string) $precioCosteUnitario
            : null;

        return $this;
    }

    public function getCosteUnitarioBase(): ?string
    {
        return $this->costeUnitarioBase;
    }

    public function setCosteUnitarioBase(string|float|int|null $costeUnitarioBase): static
    {
        $this->costeUnitarioBase = $costeUnitarioBase !== null
            ? (string) $costeUnitarioBase
            : null;

        return $this;
    }

    public function getPorcentajeIva(): ?string
    {
        return $this->porcentajeIva;
    }

    public function setPorcentajeIva(string|float|int|null $porcentajeIva): static
    {
        $this->porcentajeIva = $porcentajeIva !== null
            ? (string) $porcentajeIva
            : null;

        return $this;
    }

    public function getImporteIvaUnitario(): ?string
    {
        return $this->importeIvaUnitario;
    }

    public function setImporteIvaUnitario(string|float|int|null $importeIvaUnitario): static
    {
        $this->importeIvaUnitario = $importeIvaUnitario !== null
            ? (string) $importeIvaUnitario
            : null;

        return $this;
    }

    public function isTieneRecargoEquivalencia(): bool
    {
        return $this->tieneRecargoEquivalencia;
    }

    public function setTieneRecargoEquivalencia(bool $tieneRecargoEquivalencia): static
    {
        $this->tieneRecargoEquivalencia = $tieneRecargoEquivalencia;

        return $this;
    }

    public function getPorcentajeRecargoEquivalencia(): ?string
    {
        return $this->porcentajeRecargoEquivalencia;
    }

    public function setPorcentajeRecargoEquivalencia(string|float|int|null $porcentajeRecargoEquivalencia): static
    {
        $this->porcentajeRecargoEquivalencia = $porcentajeRecargoEquivalencia !== null
            ? (string) $porcentajeRecargoEquivalencia
            : null;

        return $this;
    }

    public function getImporteRecargoUnitario(): ?string
    {
        return $this->importeRecargoUnitario;
    }

    public function setImporteRecargoUnitario(string|float|int|null $importeRecargoUnitario): static
    {
        $this->importeRecargoUnitario = $importeRecargoUnitario !== null
            ? (string) $importeRecargoUnitario
            : null;

        return $this;
    }

    // =========================
    // MÉTODOS AUXILIARES
    // =========================

    // Devuelve el total unitario calculado.
    public function calcularTotalUnitario(): float
    {
        return
            (float) $this->costeUnitarioBase +
            (float) $this->importeIvaUnitario +
            (float) $this->importeRecargoUnitario;
    }

    // Devuelve el porcentaje fiscal total.
    public function getPorcentajeFiscalTotal(): float
    {
        return
            (float) $this->porcentajeIva +
            (float) $this->porcentajeRecargoEquivalencia;
    }

    // Devuelve si el importe tiene cualquier tipo de impuesto.
    public function tieneImpuestos(): bool
    {
        return
            (float) $this->porcentajeIva > 0 ||
            (float) $this->porcentajeRecargoEquivalencia > 0;
    }

    // Devuelve el importe unitario total de impuestos.
    public function getImporteImpuestosUnitario(): float
    {
        return
            (float) $this->importeIvaUnitario +
            (float) $this->importeRecargoUnitario;
    }
}