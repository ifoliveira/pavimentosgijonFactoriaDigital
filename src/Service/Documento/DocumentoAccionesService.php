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
        $estaNegociacion = $estadoComercial === 'negociacion';
        $estaEntregado = $estadoComercial === 'entregado';
        $estaAceptado = $estadoComercial === 'aceptado';
        $estaRechazado = $estadoComercial === 'rechazado';
        $estaConvertido = $estadoComercial === 'convertido';

        $estaCobrado = $estadoCobro === 'cobrado';

        $bloqueado = $documento->isTicketTpv() || $estaRechazado || $estaAceptado || $estaConvertido || $estaCobrado;

        return [
            // Cabecera / edición general
            'puedeEditarCabecera' => !$bloqueado,
            'puedeEditarLineas' => !$bloqueado,
            'puedeAnadirLinea' => !$bloqueado,
            'puedeBorrarLinea' => !$bloqueado,
            'puedeRecalcular' => !$bloqueado,

            // Flujo presupuesto
            'puedeEntregar' => $esPresupuesto && ($estaBorrador || $estaNegociacion),
            'puedeGenerarPdf' => $esPresupuesto && ($estaEntregado || $estaConvertido ||$estaBorrador || $estaNegociacion),
            'puedeAceptar' => $esPresupuesto && $estaEntregado && $documento->getCliente() !== null,
            'puedeRechazar' => $esPresupuesto && $estaEntregado,
            'requiereClienteParaAceptar' => $esPresupuesto && $estaEntregado && $documento->getCliente() === null,
            'puedeConvertirAFactura' => $esPresupuesto && $estaAceptado,

            // Flujo cobro
            'puedeRegistrarCobro' => ($esFactura || $esTicket) && !$documento->isTicketTpv(),

            // Informativas
            'esEditable' => !$bloqueado,
            'estaBloqueado' => $bloqueado,

            // Etiquetas opcionales por si te ayudan en Twig
            'mensajeBloqueo' => $this->getMensajeBloqueo($documento),
        ];
    }

    private function getMensajeBloqueo(Documento $documento): ?string
    {
        if ($documento->isTicketTpv()) {
            return 'Ticket emitido por el TPV. Las rectificaciones y devoluciones se gestionan desde el TPV.';
        }

        return match ($documento->getEstadoComercial()) {
            'rechazado' => 'El documento está rechazado y no se puede modificar.',
            'aceptado' => 'El presupuesto está aceptado y no se puede modificar. Crea un presupuesto complementario para cambiar el importe.',
            'convertido' => 'El presupuesto ya fue convertido y no se puede modificar.',
            default => $documento->getEstadoCobro() === 'cobrado'
                ? 'El documento está cobrado y no se puede modificar.'
                : null,
        };
    }
}
