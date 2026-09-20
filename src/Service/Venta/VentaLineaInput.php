<?php

namespace App\Service\Venta;

final class VentaLineaInput
{
    public function __construct(
        public readonly int $productoId,
        public readonly int $cantidad,
        public readonly float $precioAplicado,
        public readonly ?float $precioOriginal = null,
        public readonly ?float $coste = null,
        public readonly float $descuento = 0.0,
        public readonly ?string $texto = null,
        public readonly ?float $tipoIva = null,
    ) {}
}
