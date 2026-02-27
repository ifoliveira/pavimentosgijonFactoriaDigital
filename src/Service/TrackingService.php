<?php

namespace App\Service;

use App\Entity\Evento;
use App\Entity\Visitante;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TrackingService
{
    private EntityManagerInterface $em;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $em,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    public function track(string $tipo, array $datos = []): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        $visitorId = $request->cookies->get('visitor_id');
        $sesionId  = $request->cookies->get('sesion_id');

        if (!$visitorId || !$sesionId) {
            return;
        }

        // Buscar visitante
        try {
            $visitante = $this->em
                ->getRepository(Visitante::class)
                ->find(\Symfony\Component\Uid\Uuid::fromString($visitorId));
        } catch (\Throwable $e) {
            return;
        }

        if (!$visitante) {
            return;
        }

        // Buscar sesión
        $sesion = $this->em
            ->getRepository(\App\Entity\Sesion::class)
            ->find($sesionId);

        if (!$sesion) {
            return;
        }

        // Crear evento
        $evento = new Evento();
        $evento->setVisitante($visitante);
        $evento->setSesion($sesion);
        $evento->setTipo($tipo);
        $evento->setDatos($datos);
        $evento->setFechaCreacion(new \DateTimeImmutable());

        $this->em->persist($evento);

        // Actualizar sesión
        $sesion->setNumeroEventos($sesion->getNumeroEventos() + 1);

        $this->em->flush();
    }
}
