<?php

namespace App\Service\WebPresupuesto\Admin;

use App\Entity\PresupuestoWebInteraccion;

final class LeadWebDecision
{
    public function __construct(
        public readonly string $estado,
        public readonly string $estadoLabel,
        public readonly ?string $siguienteAccion,
        public readonly ?string $siguienteAccionLabel,
        public readonly string $motivo,
        public readonly bool $requiereIntervencion,
        public readonly ?PresupuestoWebInteraccion $interaccionBase = null,
    ) {
    }
}
