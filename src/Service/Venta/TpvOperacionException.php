<?php

namespace App\Service\Venta;

final class TpvOperacionException extends \RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly int $status = 409,
    ) {
        parent::__construct($mensaje);
    }
}
