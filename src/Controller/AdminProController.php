<?php
namespace App\Controller;

use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\CestasRepository;
use App\Repository\EconomicpresuRepository;
use App\Repository\EfectivoRepository;
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
    public function index(PresupuestosRepository $presupuestosRepository): Response
    {
        return $this->render('admin_pro/dashboard.html.twig', [
            'presupuestos'      => $presupuestosRepository->findByEstadoPe(9),
            'presupuestospdte'  => $presupuestosRepository->findByEconomicPresuDebehaberAndEstado(),
        ]);
    }

    #[Route('/flujocaja', name: 'flujocaja')]
    public function flujocaja(
        Request $request,
        BancoRepository $bancoRepository,
        EfectivoRepository $efectivoRepository
    ): Response {
        $forecast = $this->forecastService->getForecastPendiente();

        $response = $this->render('admin_pro/indexpro.html.twig', [
            'controller_name'   => 'AdminProController',
            'bancototal'        => $bancoRepository->totalBanco(),
            'gastos'            => $efectivoRepository->totalefectivo(),
            'cookies'           => $request->cookies->get('Mensaje', ''),
            'forecast'          => $forecast['list'],
            'forecastChartData' => $forecast['chartData'],
        ]);

        return $response;
    }

    #[Route('/resumenventas', name: 'resumenventas')]
    public function resumenventas(
        Request $request,
        DetallecestaRepository $detallecestaRepository,
        BancoRepository $bancoRepository,
        EfectivoRepository $efectivoRepository
    ): Response {
        $anio     = $this->getAnio($request);
        $forecast = $this->forecastService->getForecastPendiente();

        $manoobratotal = intval($efectivoRepository->manoobraEfectivo()["sum(importe_ef)"])
            + intval($bancoRepository->manoobraBanco()["importe"]);

        $response = $this->render('admin_pro/index.html.twig', [
            'anio'              => $anio,
            'controller_name'   => 'AdminProController',
            'ventastotal'       => $detallecestaRepository->ventasporMesPresupuesto($anio),
            'ventaefetotal'     => $detallecestaRepository->ventasporMesDiaaDia($anio),
            'manoobratotal'     => $manoobratotal,
            'cookies'           => $request->cookies->get('Mensaje', ''),
            'gastos'            => $efectivoRepository->totalefectivo(),
            'bancototal'        => $bancoRepository->totalBanco(),
            'forecast'          => $forecast['list'],
            'forecastChartData' => $forecast['chartData'],
        ]);


        return $response;
    }

    #[Route('/contabilidad', name: 'admin_contabilidad')]
    public function contabilidad(
        Request $request,
        EconomicpresuRepository $economicpresuRepository,
        DetallecestaRepository $detallecestaRepository,
        BancoRepository $bancoRepository,
        EfectivoRepository $efectivoRepository
    ): Response {
        $anio     = $this->getAnio($request);
        $totales  = $this->cestasService->getTotalesTicketsSnal();

        return $this->render('admin_pro/contabilidad.html.twig', [
            'anio'                => $anio,
            'controller_name'     => 'AdminProController',
            'bancototal'          => $bancoRepository->totalBanco(),
            'ventaspdtefinalizar' => $economicpresuRepository->pendienteYaCobrado($anio)["pagototalPdte"],
            'efectivototal'       => $efectivoRepository->totalefectivo(),
            'beneficio'           => $detallecestaRepository->beneficioTotal($anio),
            'pendiente'           => $totales['pendiente'],
            'cobrosMo'            => $economicpresuRepository->cobrototal($anio)["cobrototalMo"],
            'pagosMo'             => $economicpresuRepository->pagototal($anio)["pagototalMo"],
            'pendientecobroMo'    => $economicpresuRepository->pendienteCobro($anio)["cobropdteMo"],
            'pendientepagoMo'     => $economicpresuRepository->pendientePago($anio)["pagopdteMo"],
            'cestas'              => $detallecestaRepository->detallescestaactual(),
        ]);
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