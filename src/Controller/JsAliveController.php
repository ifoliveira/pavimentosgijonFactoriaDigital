<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\VisitanteRepository;
use App\Repository\SesionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Evento;

class JsAliveController extends AbstractController
{
    #[Route('/js-alive', name: 'js_alive', methods: ['POST'])]
    public function __invoke(
        Request $request,
        VisitanteRepository $visitanteRepository,
        SesionRepository $sesionRepository,
        EntityManagerInterface $em
    ): Response {

        $visitorId = $request->cookies->get('visitor_id');

        if (!$visitorId) {
            return new Response('no-cookie', 200);
        }

        $visitante = $visitanteRepository->findOneBy(['id' => $visitorId]);

        if (!$visitante) {
            return new Response('no-visitante', 200);
        }

        // Recuperar la última sesión de ese visitante
        $sesion = $sesionRepository->findOneBy(
            ['visitante' => $visitante],
            ['id' => 'DESC']   // o createdAt DESC si lo tienes
        );

        if (!$sesion) {
            return new Response('no-session', 200);
        }

        if (!$sesion->isJsConfirmed()) {
            $sesion->setJsConfirmed(true);
            $em->flush();
        }

        return new Response('ok');

    }

    #[Route('/track-event', name: 'track_event', methods: ['POST'])]
    public function trackEvent(
        Request $request,
        VisitanteRepository $visitanteRepository,
        SesionRepository $sesionRepository,
        EntityManagerInterface $em
    ): Response {

        $data = json_decode($request->getContent(), true);
        

        if (!$data) {
            return new Response('no-data', 400);
        }

        $visitorId = $request->cookies->get('visitor_id');

        if (!$visitorId) {
            return new Response('no-cookie', 200);
        }

        $visitante = $visitanteRepository->findOneBy(['id' => $visitorId]);

        if (!$visitante) {
            return new Response('no-visitor', 200);
        }

        // Recuperar la última sesión de ese visitante
        $sesion = $sesionRepository->findOneBy(
            ['visitante' => $visitante],
            ['id' => 'DESC']   // o createdAt DESC si lo tienes
        );

        if (!$sesion) {
            return new Response('no-session', 200);
        }


        $evento = new Evento();
        $evento->setVisitante($visitante);
        $evento->setTipo($data['evento']);
        $evento->setDatos(['ruta' => $data['url'] ?? null]);
        $evento->setFechaCreacion(new \DateTimeImmutable());
        $evento->setSesion($sesion);
        $em->persist($evento);
        $em->flush();

        return new Response('ok', 200);

    }

}
