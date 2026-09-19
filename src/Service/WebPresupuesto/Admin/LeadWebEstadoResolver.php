<?php

namespace App\Service\WebPresupuesto\Admin;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebInteraccion;
use App\Enum\TipoPresupuestoWebInteraccion;

final class LeadWebEstadoResolver
{
    /**
     * @param PresupuestoWebInteraccion[] $interaccionesOrdenadas Nuevas primero.
     */
    public function resolver(PresupuestoWeb $presupuesto, array $interaccionesOrdenadas): LeadWebDecision
    {
        $interaccion = $this->interaccionBase($interaccionesOrdenadas);

        if ($interaccion !== null) {
            return $this->desdeInteraccion($interaccion, $presupuesto);
        }

        if ($presupuesto->getNumeroVisualizaciones() > 0) {
            return new LeadWebDecision(
                'PRESUPUESTO_VISTO',
                'Presupuesto visto',
                'SEGUIMIENTO_AUTOMATICO',
                'Seguimiento automático',
                'El cliente ha abierto el presupuesto, pero todavía no ha realizado ninguna acción.',
                false
            );
        }

        return new LeadWebDecision(
            'PRESUPUESTO_ENVIADO',
            'Presupuesto enviado',
            'SEGUIMIENTO_AUTOMATICO',
            'Seguimiento automático',
            'El presupuesto está enviado y aún no consta ninguna visualización.',
            false
        );
    }

    /**
     * @param PresupuestoWebInteraccion[] $interaccionesOrdenadas
     */
    private function interaccionBase(array $interaccionesOrdenadas): ?PresupuestoWebInteraccion
    {
        usort($interaccionesOrdenadas, function (PresupuestoWebInteraccion $a, PresupuestoWebInteraccion $b): int {
            $fecha = $b->getCreatedAt() <=> $a->getCreatedAt();

            if ($fecha !== 0) {
                return $fecha;
            }

            return $this->prioridad($b->getTipo()) <=> $this->prioridad($a->getTipo());
        });

        return $interaccionesOrdenadas[0] ?? null;
    }

    private function desdeInteraccion(PresupuestoWebInteraccion $interaccion, PresupuestoWeb $presupuesto): LeadWebDecision
    {
        $fecha = $interaccion->getCreatedAt()->format('d/m/Y H:i');

        return match ($interaccion->getTipo()) {
            TipoPresupuestoWebInteraccion::NO_CONTINUA => new LeadWebDecision(
                'CERRADO',
                'Cerrado',
                null,
                null,
                sprintf('El cliente cerró el seguimiento el %s.', $fecha),
                false,
                $interaccion
            ),
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION => new LeadWebDecision(
                'VISITA_SOLICITADA',
                'Visita solicitada',
                'CONFIRMAR_VISITA',
                'Confirmar visita',
                sprintf('El cliente solicitó una medición el %s y facilitó los datos necesarios para preparar el siguiente paso.', $fecha),
                true,
                $interaccion
            ),
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO => new LeadWebDecision(
                'CONTACTO_SOLICITADO',
                'Contacto solicitado',
                'LLAMAR',
                'Llamar',
                sprintf('El cliente pidió que habléis el %s y dejó teléfono y preferencia de contacto.', $fecha),
                true,
                $interaccion
            ),
            TipoPresupuestoWebInteraccion::CAMBIO => new LeadWebDecision(
                'CAMBIO_SOLICITADO',
                'Cambio solicitado',
                'REVISAR_CAMBIO',
                'Revisar cambio',
                sprintf('El cliente indicó el %s que quiere revisar una parte del presupuesto.', $fecha),
                true,
                $interaccion
            ),
            TipoPresupuestoWebInteraccion::DUDA => new LeadWebDecision(
                'DUDA_PENDIENTE',
                'Duda pendiente',
                'RESPONDER_DUDA',
                'Responder duda',
                sprintf('El cliente dejó una duda sobre el presupuesto el %s.', $fecha),
                true,
                $interaccion
            ),
        };
    }

    private function prioridad(TipoPresupuestoWebInteraccion $tipo): int
    {
        return match ($tipo) {
            TipoPresupuestoWebInteraccion::NO_CONTINUA => 50,
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION => 40,
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO => 30,
            TipoPresupuestoWebInteraccion::CAMBIO => 20,
            TipoPresupuestoWebInteraccion::DUDA => 10,
        };
    }
}
