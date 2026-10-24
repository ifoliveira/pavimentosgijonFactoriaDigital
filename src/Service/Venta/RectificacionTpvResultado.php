<?php

namespace App\Service\Venta;

use App\Entity\Documento;

final class RectificacionTpvResultado
{
    public function __construct(
        public readonly Documento $documento,
        public readonly bool $idempotente,
    ) {}

    public function toArray(): array
    {
        $doc = $this->documento;
        $original = $doc->getDocumentoRectificado();

        return [
            'success' => true,
            'idempotente' => $this->idempotente,
            'id' => $doc->getId(),
            'uuid' => $doc->getTpvUuid(),
            'numeroTicket' => $doc->getNumeroFormateado(),
            'numeroTicketLocal' => $doc->getTpvNumeroTicketLocal(),
            'tipo' => 'total',
            'motivo' => $doc->getMotivoRectificacion(),
            'total' => (float) $doc->getTotal(),
            'ticketOriginal' => [
                'id' => $original->getId(),
                'uuid' => $original->getTpvUuid(),
                'numeroTicket' => $original->getNumeroFormateado(),
                'fecha' => $original->getFechaEmision()->format('Y-m-d'),
            ],
            'estadoReembolso' => $doc->getTpvReembolsoUuid() !== null ? 'realizado' : 'pendiente',
            'uuidReembolso' => $doc->getTpvReembolsoUuid(),
        ];
    }
}
