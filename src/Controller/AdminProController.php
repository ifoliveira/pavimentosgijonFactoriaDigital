<?php

namespace App\Controller;

use App\Repository\BancoRepository;
Use App\MisClases\Banks_N43;
use App\Entity\Banco;
use App\Entity\Cestas;
use App\MisClases\Importar_Ticket;
use App\MisClases\Meteo;
use App\Repository\DetallecestaRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\CestasRepository;
use App\Repository\EconomicpresuRepository;
use App\Repository\EfectivoRepository;
use App\Repository\ForecastRepository;
use App\Repository\PagosRepository;
use App\Repository\TiposmovimientoRepository;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\ORM\Mapping\Id;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\MisClases\FacturaPdfToJsonService;



class AdminProController extends AbstractController
{

    /**
     * @Route("/admin", name="admin_pro")
     */
    public function index(Request $request, PresupuestosRepository $presupuestosRepository): Response
    {

    // Obtenemos todos los presupuestos para mostrarlos en el dashboard
    $presupuestos = $presupuestosRepository->findByEstadoPe(9);
    // Accedemos al primer presupuesto
    $presupuesto = $presupuestos[0] ?? null;

    $presupuestospdte = $presupuestosRepository->findByEconomicPresuDebehaberAndEstado();

    return $this->render('admin_pro/dashboard.html.twig', [
        'presupuestos' => $presupuestos,
        'presupuestospdte' => $presupuestospdte,
    ]);
             

    }

    /**
     * @Route("/admin/flujocaja", name="flujocaja")
     */
    public function flujocaja(Request $request, DetallecestaRepository $detallecestaRepository, TiposmovimientoRepository $tiposmovimientoRepository, BancoRepository $bancoRepository, CestasRepository $cestasRepository, EfectivoRepository $efectivoRepository, ForecastRepository $forecastRepository): Response
    {

        $anio = $request->query->getInt('anio', (int) date('Y'));   
        $bancototal = $bancoRepository->totalBanco();
        
        $ventahistefect = $cestasRepository->ventaefetotal()["ventatotalef"];
        $efectivototal = $efectivoRepository->totalefectivo();
        $manoobratotal = intval($efectivoRepository->manoobraEfectivo()["sum(importe_ef)"]) + intval($bancoRepository->manoobraBanco()["importe"]) ;
        $ventasmestotal = $detallecestaRepository->ventasporMesPresupuesto($anio);
        $ventaefetotal = $detallecestaRepository->ventasporMesDiaaDia($anio);


        $forecast = $forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        $forecast = $forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );

        $cookies = $request->cookies->get('Mensaje');

        $response = $this->render('admin_pro/index.html.twig', [
            'anio' => $anio, // <-- esta línea es clave
            'controller_name' => 'AdminProController',
            'bancototal' => $bancototal,
            'ventastotal' => $ventasmestotal,
            'ventahistefect' => $ventahistefect,
            'ventaefetotal' => $ventaefetotal,
            'gastos' => $efectivototal,
            'forecast' => $forecast,
            'manoobratotal' => $manoobratotal,
            'cookies' => $cookies,

        ]);
             
        $response->headers->setCookie(new Cookie('Mensaje', 'No mostrar', time() + 3600 * 18));
 
        return $response;
    }

    /**
     * @Route("/admin/contabilidad", name="admin_contabilidad")
     */
    public function contabilidad(Request $request,
                                 EconomicpresuRepository $economicpresuRepository,  
                                 DetallecestaRepository $detallecestaRepository, 
                                 BancoRepository $bancoRepository, 
                                 CestasRepository $cestasRepository, 
                                 EfectivoRepository $efectivoRepository, 
                                 ForecastRepository $forecastRepository): Response
    {
        $anio = $request->query->getInt('anio', (int) date('Y'));

        $ventas = 0;
        $pagos = 0;
        $cobrosMo = $economicpresuRepository->cobrototal($anio)["cobrototalMo"];
        $pagosMo = $economicpresuRepository->pagototal($anio)["pagototalMo"];
        $pendientecobroMo = $economicpresuRepository->pendienteCobro($anio)["cobropdteMo"];
        $pendientepagoMo = $economicpresuRepository->pendientePago($anio)["pagopdteMo"];
        $ventapdtfinalizar  =$economicpresuRepository->pendienteYaCobrado($anio)["pagototalPdte"];
        $bancototal = $bancoRepository->totalBanco();
        $efectivototal = $efectivoRepository->totalefectivo();
        $beneficio = $detallecestaRepository->beneficioTotal($anio);
        $ticketspdte = $cestasRepository->ticketssnal();
        foreach ($ticketspdte as $ticket){
            $ventas = $ticket->getImporteTotCs() + $ventas;
            foreach ($ticket->getPagos() as $pago){
                $pagos = $pago->getImportePg() + $pagos;
            }
        }
        $pendiente = $ventas - $pagos;


        $response = $this->render('admin_pro/contabilidad.html.twig', [
            'anio' => $anio, // <-- esta línea es clave
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
            'cestas' => $detallecestaRepository->detallescestaactual(),

        ]);
             
        return $response;
    }

    /**
     * @Route("/admin/importe/ajax", name="detalle_precio", methods={"GET","POST"})
     */
    public function importeajax(Request $request): jsonResponse
    {
        $jsonData = array();
        
        $id = $request->query->get('id');
        $importe = $request->query->get('importe');
        $entityManager = $this->getDoctrine()->getManager();
                
        $actualizar = $entityManager->getRepository('App\Entity\Detallecesta')->findOneBy(['id'=> $id]);

        //actualizamos la cantidad
        $actualizar->setprecioDc($importe);
        $entityManager->persist($actualizar);
        $entityManager->flush();

        //Volver a crear apartado del loop de la pantalla
        $jsonData[0]= $id;

        return new jsonResponse($jsonData); 

    }      

    /**
     * @Route("/admin/importarTk", name="importarTk", methods={"GET","POST"})
     */
    public function importarTk(Request $request , DetallecestaRepository $detallecestaRepository)
    {
        $datos = new Importar_Ticket;
        $cestas = $datos->devolertickets();
        return $this->render('ticket.html.twig', [
            'cestas' => $cestas,
        ]);
    }

    /**
     * @Route("/admin/test/factura", name="test_factura", methods={"GET","POST"})
     */
    public function testFactura(FacturaPdfToJsonService $servicio): JsonResponse
    {
        // Cambia esta ruta por un archivo real de tu proyecto
        $rutaPdf = __DIR__ . '/../../var/facturas/FACTURA_TEST.pdf';

        // Ejecutar el servicio
        $resultado = $servicio->procesarFacturaPdf($rutaPdf);

        // Mostrar el resultado en el navegador
        return $this->json($resultado);
    }    

   /**
     * @Route("/admin/test/mock-factura", name="test_mock_factura", methods={"GET","POST"})
     */

    public function testMock(): JsonResponse
    {
        $json = file_get_contents(__DIR__ . '/../../var/facturas/factura_mock.json');
        $datos = json_decode($json, true);
    
        // Aquí es donde harás tu lógica real:
        // Ejemplo: recorrer artículos
        foreach ($datos['vencimientos'] as $vencimiento) {
            dump($vencimiento['fecha'], $vencimiento['importe']);
        }
            die;
        // Mostrar como JSON
        return $this->json($datos);
    }    

}
