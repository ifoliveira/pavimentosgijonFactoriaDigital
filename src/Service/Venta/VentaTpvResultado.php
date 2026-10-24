<?php

namespace App\Service\Venta;

use App\Entity\Cestas;
use App\Entity\Documento;

final class VentaTpvResultado
{
    public function __construct(
        public readonly Documento|Cestas $venta,
        public readonly bool $idempotente,
    ) {}

    public function toArray(): array
    {
        $venta = $this->venta;
        $esDocumento = $venta instanceof Documento;

        return [
            'success' => true,
            'idempotente' => $this->idempotente,
            'id' => $venta->getId(),
            'numeroTicket' => $esDocumento ? $venta->getNumeroFormateado() : $venta->getNumticketCs(),
            'uuid' => $venta->getTpvUuid(),
            'numeroTicketLocal' => $venta->getTpvNumeroTicketLocal(),
            'caja' => $venta->getTpvCaja(),
            'formaPago' => $esDocumento ? $venta->getTpvFormaPago() : $venta->getTipopagoCs(),
            'total' => $esDocumento ? (float) $venta->getTotal() : $venta->getImporteTotCs(),
            'estado' => $esDocumento ? 2 : $venta->getEstadoCs(),
        ];
    }
}
