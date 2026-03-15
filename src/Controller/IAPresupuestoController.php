<?php

namespace App\Controller;

use App\Entity\PresupuestosLead;
use App\Repository\PresupuestosLeadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MotorDePasosService;
use App\Service\InterpretadorIAService;
use App\Service\PresupuestoCalculatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\TelegramNotifierService as TelegramNotifier;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class IAPresupuestoController extends AbstractController
{
    // ─────────────────────────────────────────────────────────────
    // Ruta existente: formulario
    // ─────────────────────────────────────────────────────────────
    #[Route('/budget/form', name: 'budget_form', methods: ['GET'])]
    public function showForm(): Response
    {
        return $this->render('ia/iapresupuesto.html.twig');
    }

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
        presupuestoCalculatorService $pdfService
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
    public function enviarPdfPorEmail(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        PresupuestosLeadRepository $leadRepo,
        presupuestoCalculatorService $pdfService,
        TelegramNotifier $notifier
    ): JsonResponse {

        $data   = json_decode($request->getContent(), true);
        $nombre = trim($data['nombre'] ?? '');
        $email  = trim($data['email']  ?? '');
        $tipo   = $data['tipo']  ?? '';
        $json   = $data['json']  ?? [];

        // Validación mínima
        if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
        }

        // ── 1. Calcular estimación para mostrarla en el email ──
        $estimacion = $pdfService->calcular($tipo, $json);
        $rangoTexto = number_format($estimacion['min'], 0, ',', '.')
                    . ' € – '
                    . number_format($estimacion['max'], 0, ',', '.')
                    . ' €';

        // ── 2. Guardar lead o actualizar si ya existe ──
        $lead = $leadRepo->findOneBy(['email' => $email]) ?? new PresupuestosLead();

        $token = $lead->getToken() ?? bin2hex(random_bytes(24));

        $lead->setNombre($nombre);
        $lead->setEmail($email);
        $lead->setTipoReforma($tipo);
        $lead->setJsonPresupuesto($json);
        $lead->setToken($token);
        $lead->setPdfDescargas(0);
        $lead->setUltimoEvento(new \DateTime());
        $lead->setTotal($estimacion['total']);
        $lead->setFechaPdf(new \DateTime());
        $lead->setSeguimientoActivo(true);
        $lead->setEmail1Enviado(false);
        $lead->setEmail2Enviado(false);

        $em->persist($lead);
        $em->flush();

        // ── 3. Generar URL pública del PDF (con token) ──
        $urlPdf = $this->generateUrl(
            'presupuesto_descargar',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // ── 4. Enviar email HTML ──
        $htmlEmail = $this->renderView('emails/presupuesto.html.twig', [
            'nombre'     => $nombre,
            'rangoTexto' => $rangoTexto,
            'min'        => $estimacion['min'],
            'max'        => $estimacion['max'],
            'tipo'       => $tipo,
            'urlPdf'     => $urlPdf,
        ]);

        $asunto = match ($tipo) {
            'ducha'       => 'Tu estimación · Cambio de bañera por ducha en Gijón',
            default       => 'Tu estimación · Reforma integral de baño en Gijón',
        };

        $emailMessage = (new Email())
            ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
            ->to($email)
            ->subject($asunto)
            ->html($htmlEmail);

        $mailer->send($emailMessage);

        // ── 5. Notificar por Telegram (opcional) ──
        $notifier->sendMessage("📩 Nuevo lead: {$nombre} | {$email} | {$tipo} | {$estimacion['total']} €");

        return new JsonResponse(['ok' => true]);
    }

// ══════════════════════════════════════════════════════════════
//  CAMBIO 1 — Nueva ruta POST /api/presupuesto/iniciar
//  Guarda el lead con token nada más terminar el chat.
//  Sin nombre ni email todavía.
//  Añade este método a IAPresupuestoController
// ══════════════════════════════════════════════════════════════
 
#[Route('/api/presupuesto/iniciar', name: 'api_presupuesto_iniciar', methods: ['POST'])]
public function iniciar(
    Request $request,
    EntityManagerInterface $em,
    PresupuestosLeadRepository $leadRepo,
    PresupuestoCalculatorService $calculator
): JsonResponse {
 
    $data = json_decode($request->getContent(), true);
    $tipo = $data['tipo'] ?? '';
    $json = $data['json'] ?? [];
 
    if (!$tipo || empty($json)) {
        return new JsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }
 
    // Calcular estimación para guardarla
    $res = $calculator->calcular($tipo, $json);
 
    // Crear lead anónimo con token
    $lead = new PresupuestosLead();
    $lead->setTipoReforma($tipo);
    $lead->setJsonPresupuesto($json);
    $lead->setTotal($res['total']);
    $lead->setManoObra(array_sum($res['mano_obra']));
    $lead->setMateriales(array_sum($res['materiales']));
    $lead->setToken(bin2hex(random_bytes(24)));
    $lead->setFechaPdf(new \DateTime());
    $lead->setSeguimientoActivo(false); // aún no tiene email, no enviamos seguimiento
    $lead->setUltimoEvento(new \DateTime());
 
    $em->persist($lead);
    $em->flush();
 
    return new JsonResponse([
        'ok'    => true,
        'token' => $lead->getToken(),
        'url'   => $this->generateUrl(
            'presupuesto_ver',
            ['token' => $lead->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ),
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
public function completar(
    Request $request,
    EntityManagerInterface $em,
    PresupuestosLeadRepository $leadRepo,
    PresupuestoCalculatorService $calculator,
    MailerInterface $mailer,
    TelegramNotifier $notifier
): JsonResponse {
 
    $data   = json_decode($request->getContent(), true);
    $token  = $data['token']  ?? '';
    $nombre = trim($data['nombre'] ?? '');
    $email  = trim($data['email']  ?? '');
 
    if (!$token || !$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return new JsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }
 
    $lead = $leadRepo->findOneBy(['token' => $token]);
    if (!$lead) {
        return new JsonResponse(['ok' => false, 'error' => 'Presupuesto no encontrado'], 404);
    }
 
    // Completar el lead
    $lead->setNombre($nombre);
    $lead->setEmail($email);
    $lead->setSeguimientoActivo(true);
    $lead->setEmail1Enviado(false);
    $lead->setEmail2Enviado(false);
    $lead->setFechaPdf(new \DateTime());
    $lead->setUltimoEvento(new \DateTime());
    $em->flush();
 
    // Calcular rango para el email
    $tipo = $lead->getTipoReforma();
    $json = $lead->getJsonPresupuesto();
    $res  = $calculator->calcular($tipo, is_string($json) ? json_decode($json, true) : $json);
 
    $min = (int) ($res['total'] * 0.92);
    $max = (int) ($res['total'] * 1.08);
    $rangoTexto = number_format($min, 0, ',', '.') . ' € – ' . number_format($max, 0, ',', '.') . ' €';
 
    // URL del PDF
    $urlPdf = $this->generateUrl(
        'presupuesto_pdf',
        ['token' => $token],
        UrlGeneratorInterface::ABSOLUTE_URL
    );
 
    // Enviar email HTML
    $htmlEmail = $this->renderView('emails/presupuesto.html.twig', [
        'nombre'     => $nombre,
        'rangoTexto' => $rangoTexto,
        'min'        => $min,
        'max'        => $max,
        'tipo'       => $tipo,
        'urlPdf'     => $urlPdf,
    ]);
 
    $asunto = match ($tipo) {
        'ducha'  => 'Tu estimación · Cambio de bañera por ducha en Gijón',
        default  => 'Tu estimación · Reforma integral de baño en Gijón',
    };
 
    $emailMsg = (new Email())
        ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
        ->to($email)
        ->subject($asunto)
        ->html($htmlEmail);
 
    $mailer->send($emailMsg);
 
    $notifier->sendMessage("📩 Lead completado: {$nombre} | {$email} | {$tipo} | {$res['total']} €");
 
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
    PresupuestoCalculatorService $calculator,
    EntityManagerInterface $em
): Response {
 
    $lead = $leadRepo->findOneBy(['token' => $token]);
    if (!$lead) {
        throw $this->createNotFoundException('Presupuesto no encontrado.');
    }
 
    $json = $lead->getJsonPresupuesto();
    if (is_string($json)) $json = json_decode($json, true);
 
    $res  = $calculator->calcular($lead->getTipoReforma(), $json);
 
    $html = $this->renderView('iapresupuesto/presupuesto.html.twig', [
        'nombre'     => $lead->getNombre(),
        'email'      => $lead->getEmail(),
        'tipo'       => $lead->getTipoReforma(),
        'total'      => $res['total'],
        'mano_obra'  => $res['mano_obra'],
        'materiales' => $res['materiales'],
    ]);
 
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
 
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
 
    $lead->setPdfDescargas(($lead->getPdfDescargas() ?? 0) + 1);
    $lead->setUltimoEvento(new \DateTime());
    $em->flush();
 
    return new Response($dompdf->output(), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="presupuesto-bano-gijon.pdf"',
    ]);
}

}