<?php

namespace App\Controller;

use App\Service\PresupuestoCalculatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use App\MisClases\TelegramNotifier;

class PresupuestoCalculateController extends AbstractController
 
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    #[Route('/api/presupuesto/calculate', name: 'api_presupuesto_calculate', methods: ['POST'])]
    public function calculate(
        Request $request,
        PresupuestoCalculatorService $calculator
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['json']) || !isset($data['tipo'])) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'JSON incompleto'
            ], 400);
        }

        $tipo = $data['tipo'];
        $json = $data['json'];

        // Cálculo
        $resultado = $calculator->calcular($tipo, $json);


        return new JsonResponse([
            'ok' => true,
            'total' => $resultado['total'],
            'mano_obra' => $resultado['mano_obra'],
            'materiales' => $resultado['materiales'],
            'detalle' => $resultado['detalle']
        ]);
    }



 #[Route('/api/presupuesto/pdf', name: 'api_presupuesto_pdf', methods: ['POST'])]
    public function pdf(
        Request $request,
        PresupuestoCalculatorService $calculator,
        TelegramNotifier $notifier
    ): Response {

        $data = json_decode($request->getContent(), true);

        $tipo = $data['tipo'];
        $json = $data['json'];
        $nombre = $data['nombre'];
        $email = $data['email'];

        // Calcular presupuesto real
        $res = $calculator->calcular($tipo, $json);

        $mensaje = implode("\n", [
                        "❓ *Descargado por * {$nombre}",
                        "💬 *Email:* {$email}",
                        "💰 *Total presupuesto:* {$res['total']} €",
                    ]);

        $notifier->sendMessage($mensaje);

        // Renderizar plantilla Twig
        $html = $this->renderView('iapresupuesto/presupuesto.html.twig', [
            'nombre' => $nombre,
            'email' => $email,
            'tipo' => $tipo,
            'total' => $res['total'],
            'mano_obra' => $res['mano_obra'],
            'materiales' => $res['materiales']
        ]);

        // Configuración de Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="presupuesto.pdf"'
            ]
        );
    }
}



?>