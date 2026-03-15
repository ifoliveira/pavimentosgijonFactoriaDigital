<?php

namespace App\Controller;

use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\CestasRepository;
use App\Repository\EconomicpresuRepository;
use App\Repository\EfectivoRepository;
use App\Repository\ForecastRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Detallecesta;

#[Route('/admin')]
class AdminProController extends AbstractController
{

    #[Route('/', name: 'admin_pro')]
    public function index(PresupuestosRepository $presupuestosRepository): Response
    {

        // Obtenemos todos los presupuestos para mostrarlos en el dashboard
        $presupuestos = $presupuestosRepository->findByEstadoPe(9);
        $presupuestospdte = $presupuestosRepository->findByEconomicPresuDebehaberAndEstado();

        return $this->render('admin_pro/dashboard.html.twig', [
            'presupuestos' => $presupuestos,
            'presupuestospdte' => $presupuestospdte,
        ]);
    }


    #[Route('/flujocaja', name: 'flujocaja')]
    public function flujocaja(
        Request $request,
        BancoRepository $bancoRepository,
        CestasRepository $cestasRepository,
        EfectivoRepository $efectivoRepository,
        ForecastRepository $forecastRepository
    ): Response {

        $bancototal = $bancoRepository->totalBanco();
        $ventahistefect = $cestasRepository->ventaefetotal()["ventatotalef"];
        $efectivototal = $efectivoRepository->totalefectivo();

        $forecastList = $forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        // Armamos un array para el gráfico
        $forecastChartData = [];
        $acumulado = 0;

        foreach ($forecastList as $item) {

            $importe = $item->getImporteFr() * -1; // Negativo si es gasto
            $acumulado += $importe;

            $forecastChartData[] = [
                'x' => $item->getFechaFr()->format('Y-m-d'),
                'y' => round($acumulado, 2)
            ];
        }

        $cookies = $request->cookies->get('Mensaje', '');

        $response = $this->render('admin_pro/indexpro.html.twig', [
            'controller_name' => 'AdminProController',
            'bancototal' => $bancototal,
            'gastos' => $efectivototal,
            'cookies' => $cookies,
            'forecast' => $forecastList,
            'forecastChartData' => $forecastChartData
        ]);

        $response->headers->setCookie(new Cookie('Mensaje', 'No mostrar', time() + 3600 * 18));

        return $response;
    }


    #[Route('/resumenventas', name: 'resumenventas')]
    public function resumenventas(
        Request $request,
        DetallecestaRepository $detallecestaRepository,
        BancoRepository $bancoRepository,
        CestasRepository $cestasRepository,
        EfectivoRepository $efectivoRepository,
        ForecastRepository $forecastRepository
    ): Response {

        $anio = $request->query->getInt('anio', (int) date('Y'));

        $bancototal = $bancoRepository->totalBanco();
        $ventahistefect = $cestasRepository->ventaefetotal()["ventatotalef"];
        $efectivototal = $efectivoRepository->totalefectivo();

        $manoobratotal = intval($efectivoRepository->manoobraEfectivo()["sum(importe_ef)"])
            + intval($bancoRepository->manoobraBanco()["importe"]);

        $ventasmestotal = $detallecestaRepository->ventasporMesPresupuesto($anio);
        $ventaefetotal = $detallecestaRepository->ventasporMesDiaaDia($anio);

        $forecastList = $forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        $forecastChartData = [];
        $acumulado = 0;

        foreach ($forecastList as $item) {

            $importe = $item->getImporteFr() * -1;
            $acumulado += $importe;

            $forecastChartData[] = [
                'x' => $item->getFechaFr()->format('Y-m-d'),
                'y' => round($acumulado, 2)
            ];
        }

        $cookies = $request->cookies->get('Mensaje', '');

        $response = $this->render('admin_pro/index.html.twig', [
            'anio' => $anio,
            'controller_name' => 'AdminProController',
            'ventastotal' => $ventasmestotal,
            'ventahistefect' => $ventahistefect,
            'ventaefetotal' => $ventaefetotal,
            'manoobratotal' => $manoobratotal,
            'cookies' => $cookies,
            'gastos' => $efectivototal,
            'bancototal' => $bancototal,
            'forecast' => $forecastList,
            'forecastChartData' => $forecastChartData
        ]);

        $response->headers->setCookie(new Cookie('Mensaje', 'No mostrar', time() + 3600 * 18));

        return $response;
    }


    #[Route('/contabilidad', name: 'admin_contabilidad')]
    public function contabilidad(
        Request $request,
        EconomicpresuRepository $economicpresuRepository,
        DetallecestaRepository $detallecestaRepository,
        BancoRepository $bancoRepository,
        CestasRepository $cestasRepository,
        EfectivoRepository $efectivoRepository
    ): Response {

        $anio = $request->query->getInt('anio', (int) date('Y'));

        $ventas = 0;
        $pagos = 0;

        $cobrosMo = $economicpresuRepository->cobrototal($anio)["cobrototalMo"];
        $pagosMo = $economicpresuRepository->pagototal($anio)["pagototalMo"];
        $pendientecobroMo = $economicpresuRepository->pendienteCobro($anio)["cobropdteMo"];
        $pendientepagoMo = $economicpresuRepository->pendientePago($anio)["pagopdteMo"];
        $ventapdtfinalizar = $economicpresuRepository->pendienteYaCobrado($anio)["pagototalPdte"];

        $bancototal = $bancoRepository->totalBanco();
        $efectivototal = $efectivoRepository->totalefectivo();
        $beneficio = $detallecestaRepository->beneficioTotal($anio);

        $ticketspdte = $cestasRepository->ticketssnal();

        foreach ($ticketspdte as $ticket) {

            $ventas += $ticket->getImporteTotCs();

            foreach ($ticket->getPagos() as $pago) {
                $pagos += $pago->getImportePg();
            }
        }

        $pendiente = $ventas - $pagos;

        return $this->render('admin_pro/contabilidad.html.twig', [
            'anio' => $anio,
            'controller_name' => 'AdminProController',
            'bancototal' => $bancototal,
            'ventaspdtefinalizar' => $ventapdtfinalizar,
            'efectivototal' => $efectivototal,
            'beneficio' => $beneficio,
            'pendiente' => $pendiente,
            'cobrosMo' => $cobrosMo,
            'pagosMo' => $pagosMo,
            'pendientecobroMo' => $pendientecobroMo,
            'pendientepagoMo' => $pendientepagoMo,
            'cestas' => $detallecestaRepository->detallescestaactual()
        ]);
    }


    #[Route('/importe/ajax', name: 'detalle_precio', methods: ['GET','POST'])]
    public function importeajax(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {

        $id = $request->query->get('id');
        $importe = $request->query->get('importe');

        $actualizar = $entityManager->getRepository(Detallecesta::class)->find($id);

        if (!$actualizar) {
            return $this->json(['error' => 'Registro no encontrado'], 404);
        }

        // Actualizamos el precio
        $actualizar->setPrecioDc($importe);

        $entityManager->persist($actualizar);
        $entityManager->flush();

        return $this->json([
            'id' => $id,
            'importe' => $importe
        ]);
    }

}