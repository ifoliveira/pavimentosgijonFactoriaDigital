<?php

namespace App\Controller;

use App\Entity\PresupuestosLead;
use App\MisClases\OpenAiService;
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
use App\MisClases\TelegramNotifier;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class IAPresupuestoController extends AbstractController
{
    private OpenAiService $openAiService;

    public function __construct(OpenAiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

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
    //  AÑADE ESTE MÉTODO a tu controlador IAPresupuestoController
    //  (o al controlador donde tienes pdf2)
    //
    //  También necesitas añadir en el constructor (o como propiedad):
    //  private EntityManagerInterface $entityManager;
    // ══════════════════════════════════════════════════════════════

    #[Route('/presupuesto/{token}', name: 'presupuesto_descargar', methods: ['GET'])]
    public function descargarPorToken(
        string $token,
        PresupuestosLeadRepository $repo,
        PresupuestoCalculatorService $calculator,
        EntityManagerInterface $em
    ): Response {

        // 1. Buscar el lead por token
        $lead = $repo->findOneBy(['token' => $token]);

        if (!$lead) {
            throw $this->createNotFoundException('Presupuesto no encontrado.');
        }

        // 2. Recuperar datos guardados
        $tipo   = $lead->getTipoReforma();
        $json   = $lead->getJsonPresupuesto();  // ← ya es array si lo guardas como json en Doctrine
        $nombre = $lead->getNombre();
        $email  = $lead->getEmail();

        // Si getJsonPresupuesto() devuelve string, descodifícalo:
        // $json = json_decode($lead->getJsonPresupuesto(), true);

        // 3. Recalcular con el mismo servicio que ya usas
        $res = $calculator->calcular($tipo, $json);

        // 4. Renderizar con el mismo template que ya tienes
        $html = $this->renderView('iapresupuesto/presupuesto.html.twig', [
            'nombre'     => $nombre,
            'email'      => $email,
            'tipo'       => $tipo,
            'total'      => $res['total'],
            'mano_obra'  => $res['mano_obra'],
            'materiales' => $res['materiales'],
        ]);

        // 5. Generar PDF con Dompdf — igual que en pdf2
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfBinary = $dompdf->output();

        // 6. Contador de descargas (opcional pero útil)
        $lead->setPdfDescargas(($lead->getPdfDescargas() ?? 0) + 1);
        $lead->setUltimoEvento(new \DateTime());
        $em->flush();

        // 7. Devolver el PDF inline (se ve en el navegador, no fuerza descarga)
        return new Response($pdfBinary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="presupuesto-bano-gijon.pdf"',
        ]);
    }
}