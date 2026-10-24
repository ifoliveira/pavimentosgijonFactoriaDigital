<?php
namespace App\Controller;

use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\CestasRepository;
use App\Repository\DocumentoCobroRepository;
use App\Repository\ProyectoCobroRepository;
use App\Repository\EfectivoRepository;
use App\Repository\DocumentoRepository;
use App\Service\ForecastService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Detallecesta;
use App\Service\CestasService;
use App\Repository\ProyectoRepository;
use App\Service\Dashboard\DashboardProyectoService;
use App\Service\FlujoCajaAnualService;

#[Route('/admin')]
class AdminProController extends AbstractController
{
    public function __construct(
        private ForecastService $forecastService,
        private CestasService $cestasService,
    ) {}

    /**
     * Método utilitario para obtener el año, por parámetro o valor actual
     */
    private function getAnio(Request $request): int
    {
        return $request->query->getInt('anio', (int) date('Y'));
    }




    #[Route('/', name: 'admin_pro')]
    public function index(
        DashboardProyectoService $dashboardProyectoService
    ): Response {
        return $this->render('admin_pro/dashboard.html.twig', [
            'proyectosDashboard' =>
                $dashboardProyectoService->getProyectosOperativos(),
        ]);
    }

    #[Route('/flujocaja', name: 'flujocaja')]
    public function flujocaja(
        Request $request,
        BancoRepository $bancoRepository,
        EfectivoRepository $efectivoRepository,
        DashboardProyectoService $dashboardProyectoService,
        FlujoCajaAnualService $flujoCajaAnualService
    ): Response {
        $bancototal = $bancoRepository->totalBanco();
        $efectivoTotal = (float) ($efectivoRepository->totalefectivo()['efectivototal'] ?? 0);
        $forecast = $this->forecastService->getForecastPendiente((float) ($bancototal['sum(importe_bn)'] ?? 0));
        $compromisosPendientes = $dashboardProyectoService->getCompromisosPendientes();
        $saldoActual = (float) ($bancototal['sum(importe_bn)'] ?? 0) + $efectivoTotal;

        $response = $this->render('admin_pro/indexpro.html.twig', [
            'controller_name'   => 'AdminProController',
            'bancototal'        => $bancototal,
            'gastos'            => ['efectivototal' => $efectivoTotal],
            'cookies'           => $request->cookies->get('Mensaje', ''),
            'forecast'          => $forecast['list'],
            'forecastChartData' => $forecast['chartData'],
            'compromisosPendientes' => $compromisosPendientes,
            'totalCompromisosPendientes' => array_sum(array_column($compromisosPendientes, 'importe')),
            'resumenAnual' => $flujoCajaAnualService->calcular($saldoActual),
        ]);

        return $response;
    }

    #[Route('/resumenventas', name: 'resumenventas')]
    public function resumenventas(
        Request $request,
        DocumentoCobroRepository $documentoCobroRepository,
        ProyectoCobroRepository $proyectoCobroRepository
    ): Response {
        $anio     = $this->getAnio($request);
        $desde = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $anio));
        $hasta = $desde->modify('+1 year');

        $tpvPorMes = $this->indexarImportesPorMes($documentoCobroRepository->getCobrosTpvPorMes($desde, $hasta));
        $proyectosPorMes = $this->indexarImportesPorMes($proyectoCobroRepository->getCobrosPorMes($desde, $hasta));

        return $this->render('admin_pro/index.html.twig', [
            'anio'              => $anio,
            'tpvPorMes'         => $tpvPorMes,
            'proyectosPorMes'   => $proyectosPorMes,
            'datosTpvGrafico'   => array_values($tpvPorMes),
            'datosProyectosGrafico' => array_values($proyectosPorMes),
            'mesesGrafico'      => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
            'totalTpv'          => array_sum($tpvPorMes),
            'totalProyectos'    => array_sum($proyectosPorMes),
        ]);
    }

    private function indexarImportesPorMes(array $filas): array
    {
        $resultado = array_fill(1, 12, 0.0);
        foreach ($filas as $fila) {
            $resultado[(int) $fila['mes']] = (float) $fila['importe'];
        }

        return $resultado;
    }

    #[Route('/contabilidad', name: 'admin_contabilidad')]
    public function contabilidad(
        Request $request,
        DocumentoCobroRepository $documentoCobroRepository,
        ProyectoCobroRepository $proyectoCobroRepository,
        ProyectoRepository $proyectoRepository,
        DocumentoRepository $documentoRepository,
        BancoRepository $bancoRepository,
        EfectivoRepository $efectivoRepository,
        DashboardProyectoService $dashboardProyectoService
    ): Response {
        $anio     = $this->getAnio($request);
        $fechaDesde = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $anio));
        $fechaHasta = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $anio + 1));
        $ventasTpv = $documentoRepository->getResumenTicketsPorPeriodo($fechaDesde, $fechaHasta);
        $facturacionProyectos = $documentoRepository->getResumenFacturacionProyectosPorPeriodo($fechaDesde, $fechaHasta);
        $costesProyectos = $dashboardProyectoService->getResumenCostesProyectos(
            $proyectoRepository->findConFacturacionPorPeriodo($fechaDesde, $fechaHasta)
        );
        $margenProyectos = (float) $facturacionProyectos['totalBase'] - $costesProyectos['costeTotalEstimado'];

        return $this->render('admin_pro/contabilidad.html.twig', [
            'anio'                => $anio,
            'controller_name'     => 'AdminProController',
            'bancototal'          => $bancoRepository->totalBanco(),
            'efectivototal'       => $efectivoRepository->totalefectivo(),
            'ventasTpv'           => $ventasTpv,
            'facturacionProyectos' => $facturacionProyectos,
            'costesProyectos'     => $costesProyectos,
            'margenProyectos'     => $margenProyectos,
            'resumenIvaTrimestres' => $documentoRepository->getResumenIvaTrimestral($fechaDesde, $fechaHasta),
            'pendiente'           => bcadd($documentoRepository->getTotalPendienteCobro(), $proyectoRepository->getTotalPendienteCobro(), 2),
            'cobradoEjercicio'    => bcadd($documentoCobroRepository->getTotalCobrado($fechaDesde, $fechaHasta), $proyectoCobroRepository->getTotalCobrado($fechaDesde, $fechaHasta), 2),
            'cobradoAcumulado'    => bcadd($documentoCobroRepository->getTotalCobrado(), $proyectoCobroRepository->getTotalCobrado(), 2),
            'movimientosEconomicos' => $this->agruparMovimientosEconomicos(
                $bancoRepository->totalesPorCategoria($anio),
                $efectivoRepository->totalesPorCategoria($anio)
            ),
        ]);
    }

    /** @param array<int, array{categoria: string, importe: float|string}> ...$grupos */
    private function agruparMovimientosEconomicos(array ...$grupos): array
    {
        $categorias = [
            'ventas' => ['label' => 'Ventas', 'tipo' => 'entrada', 'importe' => 0.0],
            'subvenciones' => ['label' => 'Subvenciones', 'tipo' => 'entrada', 'importe' => 0.0],
            'inicial' => ['label' => 'Inicial', 'tipo' => 'entrada', 'importe' => 0.0],
            'proveedores' => ['label' => 'Proveedores', 'tipo' => 'salida', 'importe' => 0.0],
            'comisiones' => ['label' => 'Comisiones', 'tipo' => 'salida', 'importe' => 0.0],
            'sueldos' => ['label' => 'Sueldos', 'tipo' => 'salida', 'importe' => 0.0],
            'hacienda' => ['label' => 'Hacienda', 'tipo' => 'salida', 'importe' => 0.0],
            'furgoneta' => ['label' => 'Furgoneta', 'tipo' => 'salida', 'importe' => 0.0],
            'gastos_fijos' => ['label' => 'Gastos fijos', 'tipo' => 'salida', 'importe' => 0.0],
            'otros' => ['label' => 'Otros', 'tipo' => 'salida', 'importe' => 0.0],
            'transferencias' => ['label' => 'Transferencias', 'tipo' => 'interno', 'importe' => 0.0],
            'sin_categoria' => ['label' => 'Sin categoría', 'tipo' => 'sin_categoria', 'importe' => 0.0],
        ];

        foreach ($grupos as $grupo) {
            foreach ($grupo as $movimiento) {
                $texto = mb_strtolower(trim((string) ($movimiento['categoria'] ?? '')));
                $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
                $clave = match (true) {
                    $texto === '' || str_contains($texto, 'sin categoria') => 'sin_categoria',
                    str_contains($texto, 'venta') => 'ventas',
                    str_contains($texto, 'subv') => 'subvenciones',
                    str_contains($texto, 'inicial') => 'inicial',
                    str_contains($texto, 'prove') => 'proveedores',
                    str_contains($texto, 'comision') => 'comisiones',
                    str_contains($texto, 'sueldo') || str_contains($texto, 'nomina') => 'sueldos',
                    str_contains($texto, 'hacienda') || str_contains($texto, 'impuesto') || str_contains($texto, 'iva') => 'hacienda',
                    str_contains($texto, 'furgoneta') || str_contains($texto, 'vehiculo') => 'furgoneta',
                    str_contains($texto, 'gasto fijo') || str_contains($texto, 'gastos fijos') => 'gastos_fijos',
                    str_contains($texto, 'transfer') => 'transferencias',
                    default => 'otros',
                };
                $categorias[$clave]['importe'] += (float) ($movimiento['importe'] ?? 0);
            }
        }

        return array_values($categorias);
    }

    #[Route('/importe/ajax', name: 'detalle_precio', methods: ['POST'])]
    public function importeajax(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $id      = $request->request->get('id');
        $importe = $request->request->get('importe');

        // Validación básica
        if (!is_numeric($importe)) {
            return $this->json(['error' => 'Importe no válido'], 400);
        }
        
        $detalle = $entityManager->getRepository(Detallecesta::class)->find($id);
        if (!$detalle) {
            return $this->json(['error' => 'Registro no encontrado'], 404);
        }
        $detalle->setPrecioDc($importe);
        $entityManager->flush();

        return $this->json(['id' => $id, 'importe' => $importe]);
    }
}
