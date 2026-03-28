<?php

namespace App\Controller;

use App\Repository\PresupuestosLeadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MotorDePasosService;
use App\Service\InterpretadorIAService;
use App\Service\PresupuestoCalculatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\TelegramNotifierService as TelegramNotifier;
use App\Service\PresupuestoLeadService;
use App\Service\PresupuestoPdfService;

class IAPresupuestoController extends AbstractController
{
   
    // ─────────────────────────────────────────────────────────────
    // Ruta existente: pasos del chat
    // ─────────────────────────────────────────────────────────────
    #[Route('/api/presupuesto/step', name: 'api_presupuesto_step', methods: ['POST'])]
    public function step(
        Request $request,
        MotorDePasosService $motor,
        InterpretadorIAService $interpretador,
        TelegramNotifier $notifier
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $tipo             = $data['tipo'];
        $paso             = $data['paso'] ?? 0;
        $respuestaUsuario = $data['respuesta'] ?? null;
        $jsonActual       = $data['json'] ?? [];

        if ($respuestaUsuario !== null) {
            $pasoConfig = $motor->obtenerPasoCondicionado($tipo, $paso - 1, $jsonActual);
            $promptIA   = $pasoConfig['config']['interpretacion'] ?? null;

            if ($pasoConfig) {
                $clave         = $pasoConfig['config']['clave'];
                $interpretacion = $interpretador->interpretar($clave, $respuestaUsuario, $promptIA);
                $jsonActual     = $interpretador->merge($jsonActual, $interpretacion);

                $mensaje = implode("\n", [
                    "❓ *IA* {$pasoConfig['config']['pregunta']}",
                    "💬 *Usuario:* {$respuestaUsuario}",
                ]);
                $notifier->sendMessage($mensaje);
            }
        }

        $proximoPasoConfig = $motor->obtenerPasoCondicionado($tipo, $paso, $jsonActual);

        if (!$proximoPasoConfig) {
            return new JsonResponse([
                'fin'      => true,
                'json'     => $jsonActual,
                'jsonYaml' => $motor->getYaml(),
            ]);
        }

        $pasoReal = $proximoPasoConfig['index'];
        $pregunta = $motor->resolverPregunta($proximoPasoConfig['config'], $jsonActual);

        return new JsonResponse([
            'fin'      => false,
            'paso'     => $pasoReal + 1,
            'pregunta' => $pregunta,
            'opciones' => $motor->resolverOpciones($proximoPasoConfig['config'], $jsonActual),
            'json'     => $jsonActual,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Ruta existente: calcular precio
    // ─────────────────────────────────────────────────────────────
    #[Route('/api/presupuesto/calculate', name: 'api_presupuesto_calculate', methods: ['POST'])]
    public function calculate(
        Request $request,
        PresupuestoCalculatorService $pdfService
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        // Tu lógica de cálculo existente aquí
        // Por ahora devuelve la estimación que usaremos en el email
        $estimacion = $pdfService->calcular($data['tipo'], $data['json']);

        return new JsonResponse([
            'ok'         => true,
            'min'        => $estimacion['min'],
            'max'        => $estimacion['max'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // RUTA MODIFICADA: en lugar de devolver el PDF,
    // guarda el lead, genera token y envía el email con el link
    // ─────────────────────────────────────────────────────────────
    #[Route('/api/presupuesto/pdf', name: 'api_presupuesto_pdf', methods: ['POST'])]
    public function enviarPdfPorEmail(Request $request, PresupuestoLeadService $leadService): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $nombre = trim($data['nombre'] ?? '');
        $email  = trim($data['email']  ?? '');
        $tipo   = $data['tipo']  ?? '';
        $json   = $data['json']  ?? [];

        if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
        }

        $leadService->guardarLeadConEmail($nombre, $email, $tipo, $json);

        return new JsonResponse(['ok' => true]);
    }

// ══════════════════════════════════════════════════════════════
//  CAMBIO 1 — Nueva ruta POST /api/presupuesto/iniciar
//  Guarda el lead con token nada más terminar el chat.
//  Sin nombre ni email todavía.
//  Añade este método a IAPresupuestoController
// ══════════════════════════════════════════════════════════════
 
#[Route('/api/presupuesto/iniciar', name: 'api_presupuesto_iniciar', methods: ['POST'])]
public function iniciar(Request $request, PresupuestoLeadService $leadService): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $tipo = $data['tipo'] ?? '';
    $json = $data['json'] ?? [];

    if (!$tipo || empty($json)) {
        return new JsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }

    $resultado = $leadService->iniciarLead($tipo, $json);

    return new JsonResponse([
        'ok' => true,
        'token' => $resultado['token'],
        'url' => $resultado['url'],
    ]);
}
 
// ══════════════════════════════════════════════════════════════
//  CAMBIO 2 — Ruta GET /presupuesto/{token}
//  Muestra la página con galería + formulario nombre/email.
//  Renombrada a 'presupuesto_ver' (antes era 'presupuesto_descargar')
// ══════════════════════════════════════════════════════════════
 
#[Route('/presupuesto/{token}', name: 'presupuesto_ver', methods: ['GET'])]
public function verPresupuesto(
    string $token,
    PresupuestosLeadRepository $leadRepo
): Response {
 
    $lead = $leadRepo->findOneBy(['token' => $token]);
 
    if (!$lead) {
        throw $this->createNotFoundException('Presupuesto no encontrado.');
    }
 
    return $this->render('ia/iapresupuesto_resultado.html.twig', [
        'lead'  => $lead,
        'token' => $token,
        'tipo'  => $lead->getTipoReforma(),
        'total' => $lead->getTotal(),
    ]);
}
 
// ══════════════════════════════════════════════════════════════
//  CAMBIO 3 — Ruta POST /api/presupuesto/completar
//  El usuario rellena nombre+email en /presupuesto/{token}
//  → completa el lead y envía el email con el PDF
// ══════════════════════════════════════════════════════════════
 
#[Route('/api/presupuesto/completar', name: 'api_presupuesto_completar', methods: ['POST'])]
public function completar(Request $request, PresupuestoLeadService $leadService): JsonResponse
{
    $data   = json_decode($request->getContent(), true);
    $token  = $data['token']  ?? '';
    $nombre = trim($data['nombre'] ?? '');
    $email  = trim($data['email']  ?? '');

    if (!$token || !$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return new JsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }

    try {
        $leadService->completarLead($token, $nombre, $email);
    } catch (\RuntimeException $e) {
        return new JsonResponse(['ok' => false, 'error' => 'Presupuesto no encontrado'], 404);
    }

    return new JsonResponse(['ok' => true]);
}
 
// ══════════════════════════════════════════════════════════════
//  CAMBIO 4 — Ruta GET /presupuesto/{token}/pdf
//  Descarga el PDF directamente desde el link del email
// ══════════════════════════════════════════════════════════════
 
#[Route('/presupuesto/{token}/pdf', name: 'presupuesto_pdf', methods: ['GET'])]
public function descargarPdf(
    string $token,
    PresupuestosLeadRepository $leadRepo,
    PresupuestoPdfService $pdfService
): Response {

    $lead = $leadRepo->findOneBy(['token' => $token]);
    if (!$lead) {
        throw $this->createNotFoundException('Presupuesto no encontrado.');
    }

    return $pdfService->generarPdfResponse($lead);
}

}
