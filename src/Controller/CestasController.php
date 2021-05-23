<?php

namespace App\Controller;

use App\MisClases\CestaUser;
use App\Entity\Cestas;
use App\Form\CestasType;
use App\Repository\CestasRepository;
use App\Repository\EstadocestasRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
// Incluir los espacios de nombres requeridos por Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * @Route("admin/cestas")
 */
class CestasController extends AbstractController
{
    /**
     * @Route("/", name="cestas_index", methods={"GET"})
     */
    public function index(CestasRepository $cestasRepository): Response
    {
        return $this->render('cestas/index.html.twig', [
            'cestas' => $cestasRepository->ticketshoy(),
        ]);
    }

    /**
     * @Route("/new", name="cestas_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $cesta = new Cestas();
        $form = $this->createForm(CestasType::class, $cesta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($cesta);
            $entityManager->flush();

            return $this->redirectToRoute('cestas_index');
        }

        return $this->render('cestas/new.html.twig', [
            'cesta' => $cesta,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="cestas_show", methods={"GET"})
     */
    public function show(Cestas $cesta): Response
    {
        return $this->render('cestas/show.html.twig', [
            'cesta' => $cesta,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="cestas_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Cestas $cesta): Response
    {
        $form = $this->createForm(CestasType::class, $cesta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('cestas_index');
        }

        return $this->render('cestas/edit.html.twig', [
            'cesta' => $cesta,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="cestas_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Cestas $cesta): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cesta->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($cesta);
            $entityManager->flush();
        }

        return $this->redirectToRoute('cestas_index');
    }


    //Imprimir el ticket solamente, no cambia el estado del ticket
    /**
     * @Route("/ticketpdf/{id}/imprimir", name="ticketpdf", methods={"GET","POST"})
     */
    public function imprimir(Request $request, Cestas $cesta): jsonResponse
    {
        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        $cestauser = new CestaUser($em);

        // valores de data ajax
        $tipopago = $request->query->get('tipopago');
        $importe = ('SI' == $request->query->get('espresu')) ? $request->query->get('importe') : $cestauser->getImporteTot($cesta->getId());
        $importesnal = ('SI' == $request->query->get('espresu')) ? $request->query->get('importesnal') : 0;
        // Configure Dompdf según sus necesidades
        $pdfOptions = new Options();
        $pdfOptions->set(['defaultFont'=>'Arial', 
                          'defaultPaperSize'=>'A6',
                          'defaultPaperOrientation'=>'portrait'] );

        
        //Instantiate Dompdf with our options
        //$dompdf = new DOMPDF($pdfOptions);
        $dompdf = new Dompdf($pdfOptions);

        // Retrieve the HTML generated in our twig file
        $html= $this->renderView('imprimir.html.twig', [
            'title' => "Impresión ticket",
            'tipopago' => $tipopago,
            'cestaId' => $cesta->getId(),
            'espresu' => $request->query->get('espresu'),
            'importe' => $importe,
            'importesnal' => $importesnal,
             ]);
        

        // Cargar HTML en Dompdf
        $dompdf->loadHtml($html);
    
        // Renderiza el HTML como PDF
        $dompdf->render();

        // Almacenar datos binarios PDF
        $output = $dompdf->output();
        setlocale(LC_ALL, 'es_ES');   
        $namepdf = strftime("%y%j%H%M%S");              
        // En este caso, queremos escribir el archivo en el directorio público.
        $publicDirectory = $this->getParameter("ticketsDir");
        // e.g /var/www/project/public/mypdf.pdf
        $pdfFilepath =  $publicDirectory . '/'. $namepdf . '.pdf';

        // Escriba el archivo en la ruta deseada
        file_put_contents($pdfFilepath, $output);
        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['namepdf' => $namepdf.'.pdf', 'importe' => $importe]);
            // display the file contents in the browser instead of downloading it
        //return $this->file($pdfFilepath , 'my_invoice.pdf', ResponseHeaderBag::DISPOSITION_INLINE);
        
       
    }

    /**
     * @Route("/{id}/finalizar", name="cestas_finalizar")
     */
    public function finalizar(Request $request, Cestas $cestas, CestasRepository $cestasRepository,EstadocestasRepository $estadocestasRepository): Response
    {   

        $tipopago = $request->query->get('tipopago');
        $numticket = $request->query->get('numticket'); 
        $importetot = $request->query->get('importe'); 
     
        // Cambiamos el estado de la cesta del presupuesto para que sea actual

        $entityManager = $this->getDoctrine()->getManager();
        $cestas->setEstadoCs(2);
        $cestas->setTipopagoCs($tipopago);
        $cestas->setNumticketCs($numticket);
        $cestas->setImporteTotCs($importetot);
        $entityManager->persist($cestas);
        $entityManager->flush();


        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData("OK");
      

    }

    /**
     * @Route("/{id}/deletelogico", name="delete_logico")
     */
    public function deletelogico(Request $request, Cestas $cestas): Response
    {   
    
        // Cambiamos el estado de la cesta del presupuesto para que sea actual

        $entityManager = $this->getDoctrine()->getManager();
        $cestas->setEstadoCs(3);
        $entityManager->persist($cestas);
        $entityManager->flush();
        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData("OK");
      

    }

    /**
     * @Route("/nuevo/ajax", name="cesta_ajax", methods={"GET","POST"})
     */
    public function ajaxcS(Request $request): jsonResponse
    {
        $jsonData = array();
        
        $datos = $request->query->get('cesta');
        $cesta = new Cestas;
        $cesta->setEstadoCs(2);
        $cesta->setUserCs(1);
        $cesta->setFechaCs(date_create ($datos[0]));
        $cesta->setImporteTotCs($datos[1]);
        $cesta->setDescuentoCs($datos[2]);
        $cesta->setTipopagoCs($datos[3]);
        $cesta->setNumticketCs($datos[4]);
        $cesta->setTimestampCs(new \DateTime());
 
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($cesta);
        $entityManager->flush();

        $jsonData[0]= $datos;

        return new jsonResponse($jsonData); 

    }  

}
