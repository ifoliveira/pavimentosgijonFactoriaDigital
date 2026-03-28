<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TrackingService;

class PublicController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function homepage(Request $request): Response
    {
        // Página principal "home" pública
        return $this->render('home/index.html.twig');
    }

    #[Route('/cambio-banera-ducha-gijon', name: 'platoducha', methods: ['GET'])]
    public function cambioBaneraDucha(): Response
    {
        return $this->render('home/cambio_banera_ducha.html.twig');
    }

    #[Route('/reforma-integral-banos-gijon', name: 'integral', methods: ['GET'])]
    public function integral(): Response
    {

        return $this->render('home/integral.html.twig');
    }     

    #[Route('/reforma-bano-en-gijon', name: 'reformabanogijon', methods: ['GET'])]
    public function reformabanogijon(): Response
    {
        return $this->render('home/reformabanogijon.html.twig');
    }

    #[Route('/mamparas-bano-gijon', name: 'mampara')]
    public function mamparas(): Response
    {
        return $this->render('home/mamparas.html.twig');
    }       

    #[Route('/contacto', name: 'contacto')]
    public function contacto(): Response
    {
        return $this->render('home/contacto.html.twig');
    }

    #[Route('/nosotros', name: 'aboutus')]
    public function aboutus(): Response
    {
        return $this->render('home/nosotros.html.twig');
    }


    #[Route('/presupuestoInmediato', name: 'iapresupuesto')]
    public function index(TrackingService $tracking, Request $request): Response
    {
        $tracking->track('click_presupuesto', [
            'referer' => $request->headers->get('referer')
        ]);

        return $this->render('ia/iapresupuesto.html.twig');
    }    



}
