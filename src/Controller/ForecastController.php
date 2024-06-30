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

/**
 * @Route("/admin/forecast")
 */
class ForecastController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="forecast_index", methods={"GET"})
     */
    public function index(ForecastRepository $forecastRepository, BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('forecast/conciliar.html.twig', [
            'pagos' => $forecastRepository->findBy(['estadoFr' => "P"], ['fechaFr' => 'DESC']),
            'bancos' => $bancoRepository->findBy(['categoria_Bn' => ['4', '2', '11'], 'conciliado' => "0"], ['fecha_Bn' => 'DESC']),
        ]);
    }

    /**
     * @Route("/consulta", name="forecast_consulta", methods={"GET"})
     */
    public function consulta(ForecastRepository $forecastRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('forecast/consulta.html.twig', [
            'forecasts' => $forecastRepository->findByEstadoFr("P"),
        ]);
    }

    /**
     * @Route("/conciliar/idBanco/crear", name="forecast_crearBanco", methods={"GET"})
     */
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

    /**
     * @Route("/conciliar/{id}", name="forecast_conc", methods={"GET"})
     */
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
         

    /**
     * @Route("/new", name="forecast_new", methods={"GET","POST"})
     */
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

    /**
     * @Route("/{id}", name="forecast_show", methods={"GET"})
     */
    public function show(Forecast $forecast , DetallecestaRepository $detallecestaRepository): Response

    {
        return $this->render('forecast/show.html.twig', [
            'forecast' => $forecast,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="forecast_edit", methods={"GET","POST"})
     */
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

    /**
     * @Route("/{id}", name="forecast_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Forecast $forecast, DetallecestaRepository $detallecestaRepository): Response

    {
        if ($this->isCsrfTokenValid('delete'.$forecast->getId(), $request->request->get('_token'))) {
            
            $this->em->remove($forecast);
            $this->em->flush();
        }

        return $this->redirectToRoute('forecast_index');
    }

    /**
     * @Route("/delete/fila", name="forecast_delete_ajax", methods={"GET","POST"})
     */
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


    /**
     * @Route("/{id}/{estado}/conciliar", name="forecast_conciliar", methods={"GET","POST"})
     */
    public function conciliar(Request $request, Forecast $forecast,  DetallecestaRepository $detallecestaRepository, string $estado): Response
    {

        $forecast->setEstadoFr($estado);
        $this->em->flush();

        return $this->redirectToRoute('forecast_index');
    }

}
