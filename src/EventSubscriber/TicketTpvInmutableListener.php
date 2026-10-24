<?php

namespace App\EventSubscriber;

use App\Entity\Documento;
use App\Entity\DocumentoCobro;
use App\Entity\DocumentoLinea;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Protege tickets TPV emitidos también frente a los formularios genéricos del ERP.
 * La rectificación inserta otro documento; nunca altera los datos de la venta.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class TicketTpvInmutableListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $uow = $event->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->protegido($entity, $uow)) {
                $this->denegar();
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->protegido($entity, $uow)) {
                continue;
            }
            $campos = array_keys($uow->getEntityChangeSet($entity));
            $permitidos = [];
            if ($entity instanceof Documento) {
                $permitidos = ['actualizadoEn'];
                if ($this->confirmandoReembolso($entity, $uow)) {
                    $permitidos = array_merge($permitidos, [
                        'estadoCobro', 'totalCobrado', 'tpvReembolsoUuid', 'tpvReembolsoHuella', 'tpvFormaPago',
                    ]);
                }
            } elseif ($entity instanceof DocumentoCobro) {
                // La conciliación puede vincular el movimiento bancario sin reescribir el cobro.
                $permitidos = ['banco'];
            }
            if (array_diff($campos, $permitidos)) {
                $this->denegar();
            }
        }

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof DocumentoLinea && !$entity instanceof DocumentoCobro) {
                continue;
            }
            $doc = $entity->getDocumento();
            if ($doc === null || !$this->esTpv($doc, $uow) || $uow->isScheduledForInsert($doc)) {
                continue;
            }
            if ($entity instanceof DocumentoCobro && $this->confirmandoReembolso($doc, $uow)
                && bccomp($entity->getImporteBruto(), $doc->getTotal(), 2) === 0) {
                continue;
            }
            $this->denegar();
        }
    }

    private function protegido(object $entity, UnitOfWork $uow): bool
    {
        if ($entity instanceof Documento) {
            return $this->esTpv($entity, $uow);
        }
        if ($entity instanceof DocumentoLinea || $entity instanceof DocumentoCobro) {
            $anterior = $uow->getEntityChangeSet($entity)['documento'][0] ?? $uow->getOriginalEntityData($entity)['documento'] ?? null;
            return ($entity->getDocumento() !== null && $this->esTpv($entity->getDocumento(), $uow))
                || ($anterior !== null && $this->esTpv($anterior, $uow));
        }
        return false;
    }

    private function esTpv(Documento $doc, UnitOfWork $uow): bool
    {
        $anterior = $uow->getOriginalEntityData($doc);
        foreach ($uow->getEntityChangeSet($doc) as $campo => $cambio) {
            $anterior[$campo] = $cambio[0];
        }
        return $doc->isTicketTpv()
            || (isset($anterior['tpvUuid']) && in_array($anterior['tipoDocumento'] ?? '', ['ticket', 'ticket_rectificativo'], true));
    }

    private function confirmandoReembolso(Documento $doc, UnitOfWork $uow): bool
    {
        return $doc->isRectificativaTpv()
            && array_key_exists('tpvReembolsoUuid', $uow->getEntityChangeSet($doc))
            && $uow->getEntityChangeSet($doc)['tpvReembolsoUuid'][0] === null
            && $doc->getTpvReembolsoUuid() !== null
            && $doc->getTpvReembolsoHuella() !== null
            && $doc->getEstadoCobro() === 'devuelto'
            && bccomp($doc->getTotalCobrado(), $doc->getTotal(), 2) === 0;
    }

    private function denegar(): never
    {
        throw new AccessDeniedHttpException('El ticket TPV emitido es inmutable. Utiliza la API de rectificaciones o de reembolso.');
    }
}
