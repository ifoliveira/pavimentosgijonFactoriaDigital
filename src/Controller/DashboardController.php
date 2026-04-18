<?php

namespace App\Controller;

use App\Repository\DocumentoRepository;
use App\Service\Documento\DocumentoCobroService;
use App\Service\Dashboard\DashboardProyectoService;
use App\Repository\ProyectoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/dashboard')]

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        DashboardProyectoService $dashboardProyectoService,
        Request $request
    ): Response {
        $nombre = trim((string) $request->query->get('nombre', ''));
        $cliente = trim((string) $request->query->get('cliente', ''));
        $telefono = trim((string) $request->query->get('telefono', ''));
        $situacion = trim((string) $request->query->get('situacion', ''));

        $hayBusqueda = $nombre || $cliente || $telefono || $situacion;

        $resultadosBusqueda = [];
        if ($hayBusqueda) {
            $resultadosBusqueda = $dashboardProyectoService->buscarProyectos(
                $nombre ?: null,
                $cliente ?: null,
                $telefono ?: null,
                $situacion ?: null
            );
        }

        return $this->render('dashboard/index.html.twig', [
            'dashboard' => $dashboardProyectoService->getDashboardData(),
            'busqueda' => [
                'nombre' => $nombre,
                'cliente' => $cliente,
                'telefono' => $telefono,
                'situacion' => $situacion,
                'hayBusqueda' => $hayBusqueda,
                'resultados' => $resultadosBusqueda,
            ],
        ]);
    }
}