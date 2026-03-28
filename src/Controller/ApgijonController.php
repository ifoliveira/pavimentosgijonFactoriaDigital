<?php

namespace App\Controller;

use App\Entity\Consultas;
use App\Entity\Visitante;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Form\LogsType;
use App\Entity\Logs;
use App\Form\ConsultasType;
use App\Repository\ConsultasRepository;
use App\Service\OpenAIClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\TelegramNotifierService;
use Dompdf\Dompdf; 
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\TrackingService;

class ApgijonController extends AbstractController
{

    private Dompdf $pdf;

    private array $precios;

    public function __construct(ParameterBagInterface $params)
    {
        $this->precios = $params->get('presupuesto');
        $this->pdf = new Dompdf();
        $this->pdf->setPaper('A4', 'portrait');
        $this->pdf->set_option('isHtml5ParserEnabled', true);
    }


    #[Route('/api/presupuesto/chat-track', name: 'api_presupuesto_track', methods: ['POST'])]
    public function trackChat(Request $request, TelegramNotifierService $notifier): JsonResponse
      {
            $data = json_decode($request->getContent() ?: '[]', true) ?: [];

            $evento   = $data['evento']   ?? 'user_interaction';
            $tipo     = $data['tipo']     ?? 'desconocido';      // "ducha" | "completo"
            $chatId   = $data['chatId']   ?? substr(sha1($request->getClientIp().microtime()), 0, 10);
            $pregunta = $data['pregunta'] ?? null;
            $respuesta= $data['respuesta']?? null;

            // Sanitizar / limitar longitud
            $esc = static function (?string $t): string {
                if ($t === null) return '—';
                $t = mb_substr($t, 0, 500);
                // Escapar Markdown de Telegram (modo Markdown estándar)
                $repl = ['*'=>'\*','_'=>'\_','['=>'\[',']'=>'\]','('=>'\(',')'=>'\)','~'=>'\~','`'=>'\`','>'=>'\>','#'=>'\#','+'=>'\+','-'=>'\-','='=>'\=','|'=>'\|','{'=>'\{','}'=>'\}','.'=>'\.','!'=>'\!'];
                return strtr($t, $repl);
            };

            $tipoLabel = match ($tipo) {
                'ducha'    => '🚿 Cambio bañera → plato',
                'completo' => '🏗️ Reforma completa',
                default    => '❔ No especificado'
            };

            // Plantillas por evento
            $titleByEvent = [
                'tipo_seleccionado'    => '🔔 *Tipo de reforma seleccionado*',
                'first_question_shown' => '🧩 *Primera pregunta mostrada*',
                'user_response'        => '✍️ *Respuesta del usuario*',
                'descargar_pdf_click'  => '📄 *Click en Descargar PDF*',
                'first_question_abandon'=> '⏳ *Posible abandono en 1ª pregunta*',
                'user_interaction'     => '💬 *Interacción en el chat*',
            ];

        
            $titulo = $titleByEvent[$evento] ?? $titleByEvent['user_interaction'];

            // Construcción del mensaje según el evento
            switch ($evento) {

                case 'first_question_shown':
                    // Primera pregunta → tipo + IA
                    $mensaje = implode("\n", [
                        "🧩 *Primera pregunta mostrada*",
                        "• Tipo: *{$esc($tipoLabel)}*",
                        "❓ *IA:* {$esc($pregunta)}",
                    ]);
                    break;

                case 'user_response':
                    // Interacción del usuario → solo su respuesta
                    $mensaje = implode("\n", [
                        "✍️ *Respuesta del usuario*",
                        "💬 {$esc($respuesta)}",
                    ]);
                    break;

                case 'tipo_seleccionado':
                    // Tipo seleccionado → solo el tipo
                    $mensaje = implode("\n", [
                        "🔔 *Tipo de reforma seleccionado*",
                        "• Tipo: *{$esc($tipoLabel)}*",
                    ]);
                    break;

                default:
                    // Otros eventos → plantilla genérica
                    $mensaje = implode("\n", [
                        "💬 *Interacción* {$esc($evento)}",
                        "❓ *IA:* {$esc($pregunta)}",
                        "💬 *Usuario:* {$esc($respuesta)}",
                    ]);
                    break;
            }


            try {
                $notifier->sendMessage($mensaje); // tu servicio ya lo envía por Telegram en Markdown
                return $this->json(['ok' => true]);
            } catch (\Throwable $e) {
                return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
        }


    #[Route('/api/presupuesto/chat', name: 'api_presupuesto_chat', methods: ['POST'])]
        public function chat(Request $request, OpenAIClient $openAIClient, TelegramNotifierService $notifier): JsonResponse
        {
            $data = json_decode($request->getContent(), true);
            $messages = $data['messages'] ?? null;

            if (count($messages) === 1 && $messages[0]['role'] === 'system') {
                $notifier->sendMessage("📥 Nuevo usuario ha iniciado un chat de presupuesto.");
            }

            if (!$messages || !is_array($messages)) {
                return $this->json(['reply' => 'No he recibido los mensajes anteriores.']);
            }

            try {
                $respuestaIA = $openAIClient->askWithHistory($messages);
            } catch (\Throwable $e) {
                return $this->json(['reply' => 'Lo siento, ha habido un error al contactar con la IA.']);
            }

            return $this->json(['reply' => $respuestaIA]);
        }

    #[Route('/mamaparas-bano-gijon', name: 'mamparas_antigua')]
    public function mamparasAntigua(): RedirectResponse
    {
        return new RedirectResponse(
            '/mamparas-bano-gijon',
            301
        );
    }


}
