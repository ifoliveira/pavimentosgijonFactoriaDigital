<?php

namespace App\Controller;

use App\Repository\PresupuestoWebRepository;
use App\Service\WebPresupuesto\PresupuestoWebAccesoService;
use App\Service\WebPresupuesto\PresupuestoWebInteraccionService;
use App\Service\WebPresupuesto\PresupuestoWebService;
use App\Service\WebPresupuesto\WebBanoConversationService;
use App\Service\WebPresupuesto\WebDuchaDebugContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class WebPresupuestoBanoController extends AbstractController
{
    private const SNAPSHOTS_SESSION_KEY = 'web_presupuesto_bano_snapshots';
    private const COMPLETED_SNAPSHOTS_SESSION_KEY = 'web_presupuesto_bano_completed_snapshots';

    #[Route('/presupuesto-bano', name: 'web_presupuesto_bano', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('web_presupuesto_ducha/index.html.twig', [
            'page_title' => 'Presupuesto de reforma de baño completo | Nueva versión',
            'page_meta_description' => 'Calcula un presupuesto de baño completo con el nuevo configurador BudgetFlow.',
            'flow_heading' => 'Reforma de baño completo',
            'intro_message' => 'Vamos a calcular tu reforma de baño completo.',
            'api_base' => '/api/presupuesto-bano',
        ]);
    }

    #[Route('/api/presupuesto-bano/start', name: 'api_web_presupuesto_bano_start', methods: ['POST'])]
    public function start(Request $request, WebBanoConversationService $conversationService): JsonResponse
    {
        return $this->json($this->guardarSnapshotSiExiste($request, $conversationService->iniciar()));
    }

    #[Route('/api/presupuesto-bano/answer', name: 'api_web_presupuesto_bano_answer', methods: ['POST'])]
    public function answer(Request $request, WebBanoConversationService $conversationService): JsonResponse
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

    #[Route('/api/presupuesto-bano/lead', name: 'api_web_presupuesto_bano_lead', methods: ['POST'])]
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
        $completedSnapshots = $request->getSession()->get(self::COMPLETED_SNAPSHOTS_SESSION_KEY, []);

        if (isset($completedSnapshots[$snapshotId])) {
            return $this->json([
                'ok' => true,
                'mensaje' => 'Presupuesto enviado',
            ]);
        }

        $snapshots = $request->getSession()->get(self::SNAPSHOTS_SESSION_KEY, []);
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
        $request->getSession()->set(self::SNAPSHOTS_SESSION_KEY, $snapshots);
        $completedSnapshots[$snapshotId] = true;
        $request->getSession()->set(self::COMPLETED_SNAPSHOTS_SESSION_KEY, $completedSnapshots);

        return $this->json([
            'ok' => true,
            'mensaje' => 'Presupuesto enviado',
        ]);
    }

    #[Route('/presupuesto-bano/ver/{token}', name: 'web_presupuesto_bano_ver', methods: ['GET'])]
    public function ver(
        string $token,
        Request $request,
        PresupuestoWebRepository $presupuestoWebRepository,
        PresupuestoWebAccesoService $accesoService
    ): Response {
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
            'visualizacionEndpoint' => $this->generateUrl('api_web_presupuesto_bano_visualizacion', [
                'token' => $presupuesto->getToken(),
            ]),
            'interaccionEndpoint' => $this->generateUrl('api_web_presupuesto_bano_interaccion', [
                'token' => $presupuesto->getToken(),
            ]),
        ]);
    }

    #[Route('/api/presupuesto-bano/ver/{token}/visualizacion', name: 'api_web_presupuesto_bano_visualizacion', methods: ['POST'])]
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

    #[Route('/api/presupuesto-bano/ver/{token}/interaccion', name: 'api_web_presupuesto_bano_interaccion', methods: ['POST'])]
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
        $snapshots = $request->getSession()->get(self::SNAPSHOTS_SESSION_KEY, []);
        $snapshots[$snapshotId] = $snapshot;
        $request->getSession()->set(self::SNAPSHOTS_SESSION_KEY, $snapshots);
        $response['snapshotId'] = $snapshotId;

        return $response;
    }
}
