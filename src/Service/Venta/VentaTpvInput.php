<?php

namespace App\Service\Venta;

final class VentaTpvInput
{
    /**
     * @param VentaLineaInput[] $lineas
     */
    public function __construct(
        public readonly string $uuid,
        public readonly \DateTimeInterface $fecha,
        public readonly string $caja,
        public readonly string $formaPago,
        public readonly array $lineas,
        public readonly ?string $numeroTicketLocal = null,
        public readonly ?float $totalInformado = null,
    ) {}
}
