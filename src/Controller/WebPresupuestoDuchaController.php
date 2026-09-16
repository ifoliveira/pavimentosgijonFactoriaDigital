<?php

namespace App\Controller;

use App\Service\WebPresupuesto\WebDuchaConversationService;
use App\Service\WebPresupuesto\WebDuchaDebugContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class WebPresupuestoDuchaController extends AbstractController
{
    #[Route('/presupuesto-ducha', name: 'web_presupuesto_ducha', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('web_presupuesto_ducha/index.html.twig');
    }

    #[Route('/api/presupuesto-ducha/start', name: 'api_web_presupuesto_ducha_start', methods: ['POST'])]
    public function start(WebDuchaConversationService $conversationService): JsonResponse
    {
        return $this->json($conversationService->iniciar());
    }

    #[Route('/api/presupuesto-ducha/answer', name: 'api_web_presupuesto_ducha_answer', methods: ['POST'])]
    public function answer(Request $request, WebDuchaConversationService $conversationService): JsonResponse
    {
        WebDuchaDebugContext::setTraceId(bin2hex(random_bytes(6)));

        $data = json_decode($request->getContent() ?: '[]', true);

        if (!is_array($data)) {
            return $this->json([
                'ok' => false,
                'error' => 'Petición no válida.',
            ], 400);
        }

        return $this->json($conversationService->responder(
            is_array($data['estado'] ?? null) ? $data['estado'] : [],
            isset($data['respuesta']) ? (string) $data['respuesta'] : null,
            is_array($data['seleccion'] ?? null) ? $data['seleccion'] : [],
        ));
    }
}
