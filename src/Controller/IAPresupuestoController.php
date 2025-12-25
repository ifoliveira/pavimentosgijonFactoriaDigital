<?php

namespace App\Controller;

use App\MisClases\OpenAiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MotorDePasosService;
use App\Service\InterpretadorIAService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\MisClases\TelegramNotifier;

class IAPresupuestoController extends AbstractController
{
    private $openAiService;

    public function __construct(OpenAiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

    /**
     * @Route("/generate-budget", name="generate_budget", methods={"POST"})
     */
    public function generateBudget(Request $request): Response
    {
        $description = $request->request->get('description');
        
        if (!$description) {
            return new Response('Please provide a description.', Response::HTTP_BAD_REQUEST);
        }

        $proposal = $this->openAiService->generateProposalFromDescription($description);

        if (!$proposal) {
            return new Response('Failed to generate the budget.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response($proposal);
    }

    /**
     * @Route("/budget/form", name="budget_form", methods={"GET"})
     */
    public function showForm(): Response
    {
        return $this->render('ia/iapresupuesto.html.twig');
    }

    #[Route('/api/presupuesto/step', name: 'api_presupuesto_step', methods: ['POST'])]
    public function step(
        Request $request,
        MotorDePasosService $motor,
        InterpretadorIAService $interpretador,
        TelegramNotifier $notifier
    ): JsonResponse {


        $data = json_decode($request->getContent(), true);

        $tipo = $data['tipo'];
        $paso = $data['paso'] ?? 0;
        $respuestaUsuario = $data['respuesta'] ?? null;
        $jsonActual = $data['json'] ?? [];

        // ───────────────────────────────────────────────
        // 1) Procesar la respuesta del usuario (si existe)
        // ───────────────────────────────────────────────
        if ($respuestaUsuario !== null) {

            // Paso previo real (el que mostró la pregunta)
            $pasoConfig = $motor->obtenerPasoCondicionado($tipo, $paso - 1, $jsonActual);
            $promptIA = $pasoConfig['config']['interpretacion'] ?? null;

            if ($pasoConfig) {
                $clave = $pasoConfig['config']['clave'];

                // La IA interpreta la respuesta → devuelve JSON limpio
                $interpretacion = $interpretador->interpretar($clave, $respuestaUsuario, $promptIA);

                // Mezclar con el JSON acumulado
                $jsonActual = $interpretador->merge($jsonActual, $interpretacion);

                $mensaje = implode("\n", [
                        "❓ *IA* {$pasoConfig['config']['pregunta']}",
                        "💬 *Usuario:* {$respuestaUsuario}",
                    ]);

                $notifier->sendMessage($mensaje);
            }
        }

        // ───────────────────────────────────────────────
        // 2) Comprobar si se han terminado todos los pasos
        // ───────────────────────────────────────────────
        $proximoPasoConfig = $motor->obtenerPasoCondicionado($tipo, $paso, $jsonActual);
        
        if (!$proximoPasoConfig) {
            return new JsonResponse([
                'fin' => true,
                'json' => $jsonActual,
                'jsonYaml' => $motor->getYaml(),
            ]);
        }
        $pasoReal = $proximoPasoConfig['index'];
        // ───────────────────────────────────────────────
        // 3) Resolver pregunta dinámica (según condiciones)
        // ───────────────────────────────────────────────
        $pregunta = $motor->resolverPregunta($proximoPasoConfig['config'], $jsonActual);

        return new JsonResponse([
            'fin' => false,
            'paso' => $pasoReal + 1,
            'pregunta' => $pregunta,
            'opciones' => $motor->resolverOpciones($proximoPasoConfig['config'], $jsonActual), // si aplica
            'json' => $jsonActual,


        ]);
    }
}
