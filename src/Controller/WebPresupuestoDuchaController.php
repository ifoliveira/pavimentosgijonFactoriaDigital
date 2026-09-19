<?php

namespace App\Controller;

use App\Repository\PresupuestoWebRepository;
use App\Service\WebPresupuesto\PresupuestoWebAccesoService;
use App\Service\WebPresupuesto\PresupuestoWebInteraccionService;
use App\Service\WebPresupuesto\WebDuchaConversationService;
use App\Service\WebPresupuesto\WebDuchaDebugContext;
use App\Service\WebPresupuesto\PresupuestoWebService;
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
    public function start(Request $request, WebDuchaConversationService $conversationService): JsonResponse
    {
        return $this->json($this->guardarSnapshotSiExiste($request, $conversationService->iniciar()));
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

        $response = $conversationService->responder(
            is_array($data['estado'] ?? null) ? $data['estado'] : [],
            isset($data['respuesta']) ? (string) $data['respuesta'] : null,
            is_array($data['seleccion'] ?? null) ? $data['seleccion'] : [],
        );

        return $this->json($this->guardarSnapshotSiExiste($request, $response));
    }

    #[Route('/api/presupuesto-ducha/lead', name: 'api_web_presupuesto_ducha_lead', methods: ['POST'])]
    public function lead(Request $request, PresupuestoWebService $presupuestoWebService): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true);

        if (!is_array($data)) {
            return $this->json([
                'ok' => false,
                'error' => 'Petición no válida.',
            ], 400);
        }

        $snapshotId = isset($data['snapshotId']) ? (string) $data['snapshotId'] : '';
        $snapshots = $request->getSession()->get('web_presupuesto_ducha_snapshots', []);
        $snapshot = is_array($snapshots[$snapshotId] ?? null) ? $snapshots[$snapshotId] : [];

        $resultado = $presupuestoWebService->crearDesdeSnapshot(
            isset($data['email']) ? (string) $data['email'] : '',
            $data['privacidad'] ?? null,
            $snapshot
        );

        if (($resultado['ok'] ?? false) !== true) {
            return $this->json([
                'ok' => false,
                'error' => $resultado['error'] ?? 'No hemos podido registrar tu solicitud.',
            ], 400);
        }

        unset($snapshots[$snapshotId]);
        $request->getSession()->set('web_presupuesto_ducha_snapshots', $snapshots);

        return $this->json([
            'ok' => true,
            'mensaje' => 'Presupuesto enviado',
        ]);
    }

    #[Route('/politica-privacidad', name: 'politica_privacidad', methods: ['GET'])]
    public function politicaPrivacidad(): Response
    {
        return $this->render('web_presupuesto_ducha/politica_privacidad.html.twig');
    }

    #[Route('/presupuesto-ducha/ver/{token}', name: 'web_presupuesto_ducha_ver', methods: ['GET'])]
    public function ver(
        string $token,
        Request $request,
        PresupuestoWebRepository $presupuestoWebRepository,
        PresupuestoWebAccesoService $accesoService
    ): Response
    {
        $presupuesto = $presupuestoWebRepository->findOneBy(['token' => $token]);

        if ($presupuesto === null) {
            throw $this->createNotFoundException('Presupuesto no encontrado.');
        }

        $comunicacionOrigen = $accesoService->resolverComunicacionOrigen(
            $presupuesto,
            $request->query->get('c')
        );
        $viewNonce = $accesoService->generarViewNonce($request->getSession(), $presupuesto, $comunicacionOrigen);

        return $this->render('web_presupuesto_ducha/ver.html.twig', [
            'presupuesto' => $presupuesto,
            'resultado' => $presupuesto->getJsonPresupuesto(),
            'jsonSolicitudBudgetFlow' => $presupuesto->getJsonSolicitudBudgetFlow(),
            'viewNonce' => $viewNonce,
            'comunicacionOrigenToken' => $comunicacionOrigen?->getTokenAcceso(),
            'visualizacionEndpoint' => $this->generateUrl('api_web_presupuesto_ducha_visualizacion', [
                'token' => $presupuesto->getToken(),
            ]),
            'interaccionEndpoint' => $this->generateUrl('api_web_presupuesto_ducha_interaccion', [
                'token' => $presupuesto->getToken(),
            ]),
        ]);
    }

    #[Route('/api/presupuesto-ducha/ver/{token}/visualizacion', name: 'api_web_presupuesto_ducha_visualizacion', methods: ['POST'])]
    public function registrarVisualizacion(
        string $token,
        Request $request,
        PresupuestoWebRepository $presupuestoWebRepository,
        PresupuestoWebAccesoService $accesoService
    ): JsonResponse {
        $presupuesto = $presupuestoWebRepository->findOneBy(['token' => $token]);

        if ($presupuesto === null) {
            return $this->json([
                'ok' => false,
                'counted' => false,
            ], 404);
        }

        $data = json_decode($request->getContent() ?: '[]', true);
        $viewNonce = is_array($data) && isset($data['viewNonce']) ? (string) $data['viewNonce'] : '';
        $resultado = $accesoService->registrarVisualizacion($request->getSession(), $presupuesto, $viewNonce);

        return $this->json($resultado, ($resultado['ok'] ?? false) ? 200 : 400);
    }

    #[Route('/api/presupuesto-ducha/ver/{token}/interaccion', name: 'api_web_presupuesto_ducha_interaccion', methods: ['POST'])]
    public function registrarInteraccion(
        string $token,
        Request $request,
        PresupuestoWebRepository $presupuestoWebRepository,
        PresupuestoWebInteraccionService $interaccionService
    ): JsonResponse {
        $presupuesto = $presupuestoWebRepository->findOneBy(['token' => $token]);

        if ($presupuesto === null) {
            return $this->json([
                'ok' => false,
                'error' => 'Presupuesto no encontrado.',
            ], 404);
        }

        $data = json_decode($request->getContent() ?: '[]', true);

        if (!is_array($data)) {
            return $this->json([
                'ok' => false,
                'error' => 'Petición no válida.',
            ], 400);
        }

        $resultado = $interaccionService->registrar($presupuesto, $data);

        if (($resultado['ok'] ?? false) !== true) {
            return $this->json([
                'ok' => false,
                'error' => $resultado['error'] ?? 'No hemos podido registrar la acción.',
            ], 400);
        }

        return $this->json([
            'ok' => true,
            'seguimientoDetenido' => $resultado['seguimientoDetenido'] ?? false,
            'comunicacionesCanceladas' => $resultado['comunicacionesCanceladas'] ?? 0,
        ]);
    }

    private function guardarSnapshotSiExiste(Request $request, array $response): array
    {
        $snapshot = $response['snapshot_presupuesto'] ?? null;
        unset($response['snapshot_presupuesto']);

        if (($response['ok'] ?? false) !== true || ($response['finalizada'] ?? false) !== true || !is_array($snapshot)) {
            return $response;
        }

        $snapshotId = bin2hex(random_bytes(16));
        $snapshots = $request->getSession()->get('web_presupuesto_ducha_snapshots', []);
        $snapshots[$snapshotId] = $snapshot;
        $request->getSession()->set('web_presupuesto_ducha_snapshots', $snapshots);
        $response['snapshotId'] = $snapshotId;

        return $response;
    }
}
