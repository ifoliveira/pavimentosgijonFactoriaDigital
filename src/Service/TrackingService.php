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

        if (!$visitorId) {
            return;
        }

        $visitante = $this->em
            ->getRepository(Visitante::class)
            ->find($visitorId);

        if (!$visitante) {
            return;
        }

        $evento = new Evento();
        $evento->setVisitante($visitante);
        $evento->setTipo($tipo);
        $evento->setDatos($datos);
        $evento->setFechaCreacion(new \DateTimeImmutable());

        $this->em->persist($evento);
        $this->em->flush();
    }
}
