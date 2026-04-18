<?php

namespace App\Service\Documento;

use App\Entity\Documento;

class DocumentoAccionesService
{
    public function getAccionesDisponibles(Documento $documento): array
    {
        $tipo = $documento->getTipoDocumento();
        $estadoComercial = $documento->getEstadoComercial();
        $estadoCobro = $documento->getEstadoCobro();

        $esPresupuesto = $tipo === 'presupuesto';
        $esFactura = $tipo === 'factura';
        $esTicket = $tipo === 'ticket';

        $estaBorrador = $estadoComercial === 'borrador';
        $estaEntregado = $estadoComercial === 'entregado';
        $estaAceptado = $estadoComercial === 'aceptado';
        $estaRechazado = $estadoComercial === 'rechazado';
        $estaConvertido = $estadoComercial === 'convertido';

        $estaCobrado = $estadoCobro === 'cobrado';

        $bloqueado = $estaRechazado || $estaConvertido || $estaCobrado;

        return [
            // Cabecera / edición general
            'puedeEditarCabecera' => !$bloqueado,
            'puedeEditarLineas' => !$bloqueado,
            'puedeAnadirLinea' => !$bloqueado,
            'puedeBorrarLinea' => !$bloqueado,
            'puedeRecalcular' => !$bloqueado,

            // Flujo presupuesto
            'puedeEntregar' => $esPresupuesto && $estaBorrador,
            'puedeGenerarPdf' => $esPresupuesto && ($estaEntregado || $estaConvertido),
            'puedeAceptar' => $esPresupuesto && $estaEntregado,
            'puedeRechazar' => $esPresupuesto && $estaEntregado,
            'puedeConvertirAFactura' => $esPresupuesto && $estaAceptado,

            // Flujo cobro
            'puedeRegistrarCobro' => ($esFactura || $esTicket) && !$estaCobrado,

            // Informativas
            'esEditable' => !$bloqueado,
            'estaBloqueado' => $bloqueado,

            // Etiquetas opcionales por si te ayudan en Twig
            'mensajeBloqueo' => $this->getMensajeBloqueo($documento),
        ];
    }

    private function getMensajeBloqueo(Documento $documento): ?string
    {
        return match ($documento->getEstadoComercial()) {
            'rechazado' => 'El documento está rechazado y no se puede modificar.',
            'convertido' => 'El presupuesto ya fue convertido y no se puede modificar.',
            default => $documento->getEstadoCobro() === 'cobrado'
                ? 'El documento está cobrado y no se puede modificar.'
                : null,
        };
    }
}
