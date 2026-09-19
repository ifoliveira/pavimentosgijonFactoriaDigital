<?php

namespace App\Service\WebPresupuesto\Admin;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebAcceso;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebInteraccion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebInteraccion;

final class LeadWebTimelineBuilder
{
    /**
     * @param PresupuestoWebInteraccion[] $interacciones
     * @return array<int, array{fecha: \DateTimeImmutable, titulo: string, descripcion: ?string, datos: array<int, array{label: string, valor: string}>}>
     */
    public function construir(PresupuestoWeb $presupuesto, array $interacciones): array
    {
        $items = [];

        foreach ($presupuesto->getComunicaciones() as $comunicacion) {
            if (!$comunicacion instanceof PresupuestoWebComunicacion) {
                continue;
            }

            $fecha = $comunicacion->getFechaEnvio() ?? $comunicacion->getFechaProgramada() ?? $comunicacion->getCreatedAt();
            $items[] = [
                'fecha' => $fecha,
                'titulo' => $this->tituloComunicacion($comunicacion),
                'descripcion' => sprintf('Estado: %s', $this->labelEstadoComunicacion($comunicacion->getEstado()->value)),
                'datos' => [],
            ];
        }

        foreach ($presupuesto->getAccesos() as $acceso) {
            if (!$acceso instanceof PresupuestoWebAcceso) {
                continue;
            }

            $origen = $acceso->getComunicacionOrigen();
            $items[] = [
                'fecha' => $acceso->getFechaAcceso(),
                'titulo' => $origen ? sprintf('Accedió desde %s', $this->labelTipoComunicacion($origen->getTipo())) : 'Nueva consulta del presupuesto',
                'descripcion' => null,
                'datos' => [],
            ];
        }

        foreach ($interacciones as $interaccion) {
            $items[] = [
                'fecha' => $interaccion->getCreatedAt(),
                'titulo' => $this->tituloInteraccion($interaccion->getTipo()),
                'descripcion' => $interaccion->getComunicacionOrigen()
                    ? sprintf('Origen: %s', $this->labelTipoComunicacion($interaccion->getComunicacionOrigen()->getTipo()))
                    : null,
                'datos' => $this->datosInteraccion($interaccion),
            ];
        }

        usort($items, static fn(array $a, array $b): int => $a['fecha'] <=> $b['fecha']);

        return $items;
    }

    private function tituloComunicacion(PresupuestoWebComunicacion $comunicacion): string
    {
        return match ($comunicacion->getTipo()) {
            TipoPresupuestoWebComunicacion::PRESUPUESTO => 'Presupuesto enviado',
            TipoPresupuestoWebComunicacion::SEGUIMIENTO => 'Seguimiento enviado',
            TipoPresupuestoWebComunicacion::CIERRE => 'Comunicación de cierre',
        };
    }

    private function tituloInteraccion(TipoPresupuestoWebInteraccion $tipo): string
    {
        return match ($tipo) {
            TipoPresupuestoWebInteraccion::DUDA => 'Duda sobre el presupuesto',
            TipoPresupuestoWebInteraccion::CAMBIO => 'Solicita revisar una parte',
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO => 'Solicita contacto',
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION => 'Solicita medición',
            TipoPresupuestoWebInteraccion::NO_CONTINUA => 'Cierra el seguimiento',
        };
    }

    private function labelTipoComunicacion(TipoPresupuestoWebComunicacion $tipo): string
    {
        return match ($tipo) {
            TipoPresupuestoWebComunicacion::PRESUPUESTO => 'presupuesto inicial',
            TipoPresupuestoWebComunicacion::SEGUIMIENTO => 'seguimiento',
            TipoPresupuestoWebComunicacion::CIERRE => 'cierre',
        };
    }

    private function labelEstadoComunicacion(string $estado): string
    {
        return match ($estado) {
            'pendiente' => 'pendiente',
            'enviada' => 'enviada',
            'error' => 'error',
            'cancelada' => 'cancelada',
            default => $estado,
        };
    }

    /**
     * @return array<int, array{label: string, valor: string}>
     */
    private function datosInteraccion(PresupuestoWebInteraccion $interaccion): array
    {
        $datos = $interaccion->getDatos() ?? [];
        $lineas = [];

        foreach ($datos as $clave => $valor) {
            if (!is_scalar($valor) || trim((string) $valor) === '') {
                continue;
            }

            $lineas[] = [
                'label' => $this->labelDato($clave),
                'valor' => $this->formatearDato((string) $valor),
            ];
        }

        return $lineas;
    }

    private function labelDato(string $clave): string
    {
        return match ($clave) {
            'telefono' => 'Teléfono',
            'direccion' => 'Dirección',
            'franja' => 'Preferencia',
            'mensaje' => 'Mensaje',
            'observaciones' => 'Observaciones',
            'componente' => 'Componente',
            default => ucfirst(str_replace('_', ' ', $clave)),
        };
    }

    private function formatearDato(string $valor): string
    {
        return match ($valor) {
            'manana' => 'Mañana',
            'tarde' => 'Tarde',
            'indiferente' => 'Me da igual',
            'plato' => 'Plato de ducha',
            'mampara' => 'Mampara',
            'griferia' => 'Grifería',
            default => $valor,
        };
    }
}
