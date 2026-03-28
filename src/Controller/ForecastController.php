<?php

namespace App\Controller;

use App\Entity\Forecast;
use App\Form\ForecastType;
use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\ForecastRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\FacturaPdfToJsonService;
use App\Repository\TiposmovimientoRepository;
use App\Repository\TipoproductoRepository;
use App\Entity\Productos;

#[Route('/admin/forecast')]
class ForecastController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }

    #[Route('/', name: 'forecast_index', methods: ['GET'])]
    public function index(ForecastRepository $forecastRepository, BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('forecast/conciliar.html.twig', [
            'pagos' => $forecastRepository->findBy(['estadoFr' => "P"], ['fechaFr' => 'DESC']),
            'bancos' => $bancoRepository->findBy(['categoria_Bn' => ['4', '2', '11'], 'conciliado' => "0"], ['fecha_Bn' => 'DESC']),
        ]);
    }

    #[Route('/consulta', name: 'forecast_consulta', methods: ['GET'])]
    public function consulta(ForecastRepository $forecastRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('forecast/consulta.html.twig', [
            'forecasts' => $forecastRepository->findByEstadoFr("P"),
        ]);
    }

    #[Route('/conciliar/idBanco/crear', name: 'forecast_crearBanco', methods: ['GET'])]
    public function crearIdBanco(Request $request, BancoRepository $bancoRepository): JsonResponse
    {    
        $bancoId = $request->query->get('banco');
        $banco = $bancoRepository->findOneBy(array('id' => $bancoId));
        $banco->setConciliado(true);   
        $forecast = New Forecast();
        $forecast->setEstadoFr('C');
        $forecast->setTipoFr($banco->getCategoriaBn());
        $forecast->setFijovarFr('V');
        $forecast->setOrigenFr("Banco");
        $forecast->setImporteFr($banco->getImporteBn());
        $forecast->setTimestamp(new \DateTime());
        $forecast->setBanco($banco);
        $forecast->setConceptoFr($banco->getConceptoBn());
        $forecast->setFechaFr($banco->getFechaBn());


        $this->em->persist($forecast);
        $this->em->persist($banco);
        $this->em->flush();

        $response = new JsonResponse();

        return $response;

    }    

    #[Route('/conciliar/{id}', name: 'forecast_conc', methods: ['GET'])]
    public function conciliarPagos(Request $request, Forecast $forecast, BancoRepository $bancoRepository): JsonResponse
    {    
        $bancoId = $request->query->get('banco');
        $banco = $bancoRepository->findOneBy(array('id' => $bancoId));
        $banco->setConciliado(true);   
        $forecast->setEstadoFr('C');
        $forecast->setImporteFr($banco->getImporteBn());
        if ($forecast->getTimestamp() == NULL){

            $forecast->setTimestamp(new \DateTime());

        }
        $forecast->setBanco($banco);
        $this->em->persist($forecast);
        $this->em->persist($banco);
        $this->em->flush();

        $response = new JsonResponse();

        return $response;

    }
         

    #[Route('/new', name: 'forecast_new', methods: ['GET','POST'])]
    public function new(Request $request , DetallecestaRepository $detallecestaRepository): Response
    {
        $forecast = new Forecast();
        $form = $this->createForm(ForecastType::class, $forecast);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->em->persist($forecast);
            $this->em->flush();

            return $this->redirectToRoute('forecast_new');      
        }

        return $this->render('forecast/new.html.twig', [
            'forecast' => $forecast,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'forecast_show', methods: ['GET'])]
    public function show(Forecast $forecast , DetallecestaRepository $detallecestaRepository): Response

    {
        return $this->render('forecast/show.html.twig', [
            'forecast' => $forecast,
        ]);
    }

    #[Route('/{id}/edit', name: 'forecast_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Forecast $forecast , DetallecestaRepository $detallecestaRepository): Response

    {
        $form = $this->createForm(ForecastType::class, $forecast);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('forecast_index');
        }

        return $this->render('forecast/edit.html.twig', [
            'forecast' => $forecast,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'forecast_delete', methods: ['DELETE'])]
    public function delete(Request $request, Forecast $forecast, DetallecestaRepository $detallecestaRepository): Response

    {
        if ($this->isCsrfTokenValid('delete'.$forecast->getId(), $request->request->get('_token'))) {
            
            $this->em->remove($forecast);
            $this->em->flush();
        }

        return $this->redirectToRoute('forecast_index');
    }

    #[Route('/delete/fila', name: 'forecast_delete_ajax', methods: ['GET','POST'])]
    public function deleteforecastajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');
        // Obtener cesta
        $forecast = $this->em->getRepository('App\Entity\Forecast')->find($datos);

        // Borrado del detalle
        $this->em->remove($forecast);
        $this->em->flush();

        $response = new JsonResponse();

        return $response;

    }  


    #[Route('/{id}/{estado}/conciliar', name: 'forecast_conciliar', methods: ['GET','POST'])]
    public function conciliar(Request $request, Forecast $forecast,  DetallecestaRepository $detallecestaRepository, string $estado): Response
    {

        $forecast->setEstadoFr($estado);
        $this->em->flush();

        return $this->redirectToRoute('forecast_index');
    }


    #[Route('/factura/subir', name: 'factura_subir', methods: ['GET', 'POST'])]
    public function subir(
        Request $request,
        FacturaPdfToJsonService $facturaPdfToJsonService
    ): Response {
        // Petición normal GET → renderiza la página
        if ($request->isMethod('GET')) {
            return $this->render('forecast/subir.html.twig');
        }

        // Petición AJAX POST → devuelve JSON
        $archivo = $request->files->get('factura');

        if (!$archivo) {
            return $this->json(['error' => 'No se ha recibido ningún archivo.'], 400);
        }

        if (strtolower($archivo->getClientOriginalExtension()) !== 'pdf') {
            return $this->json(['error' => 'Solo se admiten archivos PDF.'], 400);
        }
        try {
            $tmpPath = sys_get_temp_dir() . '/' . uniqid('factura_', true) . '.pdf';
            $archivo->move(dirname($tmpPath), basename($tmpPath));
            $resultado = $facturaPdfToJsonService->procesarFacturaPdf($tmpPath);
            @unlink($tmpPath);

            return $this->json(['resultado' => $resultado]);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Error procesando la factura: ' . $e->getMessage()], 500);
        }
    }
    #[Route('/factura/forecast', name: 'factura_forecast', methods: ['POST'])]
    public function insertarForecast(
        Request $request,
        EntityManagerInterface $em,
        TiposmovimientoRepository $tiposRepo
    ): Response {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['vencimientos']) || empty($data['concepto'])) {
            return $this->json(['error' => 'Datos incompletos.'], 400);
        }

        // Busca el tipo "Proveedor" por descripción en lugar de por ID fijo
        $tipo = $tiposRepo->findOneBy(['descripcionTm' => 'Proveedor']);
        if (!$tipo) {
            return $this->json(['error' => 'No se encontró el tipo de movimiento "Proveedor".'], 404);
        }

        $insertados = 0;

        foreach ($data['vencimientos'] as $venc) {
            if (empty($venc['fecha']) || !isset($venc['importe'])) {
                continue;
            }

            $forecast = new Forecast();
            $forecast->setTipoFr($tipo);
            $forecast->setConceptoFr($data['concepto']);
            $forecast->setFechaFr(new \DateTime($venc['fecha']));
            $forecast->setImporteFr((float) $venc['importe']*-1);
            $forecast->setOrigenFr('Banco');

            $em->persist($forecast);
            $insertados++;
        }

        if ($insertados === 0) {
            return $this->json(['error' => 'No hay vencimientos válidos para insertar.'], 400);
        }

        $em->flush();

        return $this->json(['ok' => true, 'insertados' => $insertados]);
    }

    #[Route('/factura/productos', name: 'factura_productos', methods: ['POST'])]
    public function insertarProductos(
        Request $request,
        EntityManagerInterface $em,
        TipoproductoRepository $tipoRepo
    ): Response {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['articulos'])) {
            return $this->json(['error' => 'No hay artículos.'], 400);
        }

        $insertados = 0;

        foreach ($data['articulos'] as $art) {
            if (empty($art['descripcion'])) continue;

            $tipo = $tipoRepo->find($art['tipo_id'] ?? 1);
            if (!$tipo) continue;

            $producto = new Productos();
            $producto->setDescripcionPd($art['descripcion']);
            $coste = round((float) $art['coste_con_iva'], 2);
            $pvpRaw = $coste * 1.50;

            // Redondea al 0.95 del euro más cercano
            $pvp = floor($pvpRaw) + 0.95;

            // Si el redondeo al .95 se pasó hacia arriba más de 1€, retrocede
            if ($pvp - $pvpRaw > 1) {
                $pvp -= 1.0;
            }

            // Si el precio exacto es más cercano (termina en .00) lo deja exacto
            // En tu caso siempre quieres .95, así que lo dejamos fijo

            $producto->setPrecioPd($coste);
            $producto->setPvpPd($pvp);
            $producto->setStockPd((int) $art['cantidad']);
            $producto->setFecAltaPd(new \DateTime());
            $producto->setTipoPdId($tipo);
            $producto->setObsoleto(false);

            $em->persist($producto);
            $insertados++;
        }

        if ($insertados === 0) {
            return $this->json(['error' => 'No hay artículos válidos.'], 400);
        }

        $em->flush();

        return $this->json(['ok' => true, 'insertados' => $insertados]);
    }
}
