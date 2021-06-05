<?php

namespace App\Controller;

use App\Entity\Presupuestos;
use App\Entity\Productos;
use App\Form\PresupuestosType;
use App\Form\ProductosType;
use App\Repository\CestasRepository;
use App\Entity\Cestas;
use App\Repository\EstadocestasRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\ProductosRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Dompdf\Dompdf;
use Dompdf\Options;


/**
 * @Route("/admin/presupuestos")
 */
class PresupuestosController extends AbstractController
{
    /**
     * @Route("/", name="presupuestos_index", methods={"GET"})
     */
    public function index(PresupuestosRepository $presupuestosRepository): Response
    {
        $estados=$presupuestosRepository->numeroestado();

        return $this->render('presupuestos/index.html.twig', [
            'presupuestos' => $presupuestosRepository->findBy([], ['id' => 'DESC']),
            'estados' => $estados,
        ]);
    }

    /**
     * @Route("/new", name="presupuestos_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $presupuesto = new Presupuestos();
        $form = $this->createForm(PresupuestosType::class, $presupuesto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($presupuesto);
            $entityManager->flush();

            return $this->redirectToRoute('presupuestos_index');
        }

        return $this->render('presupuestos/new.html.twig', [
            'presupuesto' => $presupuesto,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="presupuestos_show",  methods={"GET","POST"})
     */
    public function show(Request $request, Presupuestos $presupuesto, ProductosRepository $productosRepository): Response
    {

        $directorio = $this->getParameter("presupuestoDir") . '/' . $presupuesto->getClientePe()->getNombreCl() . ' ' . $presupuesto->getFechainiPe()->format('Y-m-d');

        $defaultData = array('message' => 'Escribe un mensaje aquí');

        $form = $this->createFormBuilder($defaultData)
            ->add('foto_pre', FileType::class)
            ->getForm();
    
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Los datos están en un array con los keys "name", "email", y "message"
            $data = $form->getData();

            try {
                $filename = bin2hex(random_bytes(6)).'.'.$data['foto_pre']->guessExtension();
                $data['foto_pre']->move($directorio. '/fotos', $filename);
                } 
            catch (FileException $e) {
                            // unable to upload the photo, give up
                                     }
            return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
        }

        $formestado = $this->createForm(PresupuestosType::class, $presupuesto, [
                'action' => $this->generateUrl('presupuestos_estado', array('id' => $presupuesto->getId(), 'estado' => $presupuesto->getEstadoPe()->getId() + 1 )),
                'method' => 'GET',
                'method' => 'POST',
              ]);

        $formmanoob = $this->createForm(PresupuestosType::class, $presupuesto);

        $formmanoob->handleRequest($request);

        if ($formmanoob->isSubmitted() && $formmanoob->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
        }

        $formestadopr = $this->createForm(PresupuestosType::class, $presupuesto);

        $formestadopr->handleRequest($request);

        if ($formestadopr->isSubmitted() && $formestadopr->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
        }
        $ruta = $directorio . '/fotos';
        if (file_exists($ruta)) {

            $ficheros = scandir($directorio . '/fotos');
            unset($ficheros[0],$ficheros[1]);
        }
        else{
            $ficheros=[];
        }


        switch ($presupuesto->getEstadoPe()->getId()) {
  //          case 4:
  //              $productos = $productosRepository->findAllgenericos();
  //              break;
  //          case 5:
  //              $productos = $productosRepository->findAllgenericos();
  //              break;
            case 6:
                $productos = $productosRepository->findAll();
                break;
            default:
                $productos = new Productos;
        }

        $producto = new Productos;
        $formprod = $this->createForm(ProductosType::class, $producto);
        $formprod->handleRequest($request);

        if ($formprod->isSubmitted() && $formprod->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($producto);
            $entityManager->flush();

            return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
        }

        return $this->render('presupuestos/show.html.twig', [
            'presupuesto' => $presupuesto,
            'form' => $form->createView(),
            'formprod' => $formprod->createView(),
            'formestado' => $formestado->createView(),
            'formmanoob' => $formmanoob->createView(),
            'formestadopr' => $formestadopr->createView(),
            'fotos' => $ficheros,
            'cestaId'=> $presupuesto->getTicket()->getId(),
            'productos' => $productos,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="presupuestos_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Presupuestos $presupuesto): Response
    {
        $form = $this->createForm(PresupuestosType::class, $presupuesto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('presupuestos_index');
        }

        return $this->render('presupuestos/edit.html.twig', [
            'presupuesto' => $presupuesto,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/{estado}/estado", name="presupuestos_estado", methods={"GET","POST"})
     */
    public function editestado(Request $request, Presupuestos $presupuesto, int $estado, EstadocestasRepository $estadocestasRepository): Response
    {
        $formestado = $this->createForm(PresupuestosType::class, $presupuesto);
        $formestado->handleRequest($request);

        if ($formestado->isSubmitted() && $formestado->isValid()) {
        
            $estadocesta = $estadocestasRepository->findOneBy(
                ['id' => $estado],
            );

            $presupuesto->setEstadoPe($estadocesta);
            $entityManager = $this->getDoctrine()->getManager();

            $entityManager->flush();


            }

                                                                                            
        return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
    }


    /**
     * @Route("/{id}/finalizar", name="presupuestos_finalizar")
     */
    public function finalizar(Request $request, Presupuestos $presupuesto, CestasRepository $cestasRepository,EstadocestasRepository $estadocestasRepository): Response
    {   

        $tipopago = $request->query->get('tipopago');

        $entityManager = $this->getDoctrine()->getManager();
       
        $estadocesta = $estadocestasRepository->findOneBy(
            ['id' => 10],
        );

        $presupuesto->setEstadoPe($estadocesta);
        $presupuesto->setTipopagototPE($tipopago);
        $entityManager->flush();

        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['cestaid' =>$presupuesto->getTicket()->getId()]);
      

    }




    /**
     * @Route("/{id}/{estado}/finestado", name="presupuestos_finestado", methods={"GET","POST"})
     */
    public function finestado(Request $request, Presupuestos $presupuesto, int $estado, EstadocestasRepository $estadocestasRepository): Response
    {
     
        $estadocesta = $estadocestasRepository->findOneBy(
            ['id' => $estado],
        );

        $presupuesto->setEstadoPe($estadocesta);
        $presupuesto->getTicket()->setEstadoCs(99);
        $entityManager = $this->getDoctrine()->getManager();

        $entityManager->flush();

                                                                                            
        return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
    }


    /**
     * @Route("/{id}/manoobra", name="presupuesto_manoobra")
     */
    public function editmano(Request $request, Presupuestos $presupuesto): JsonResponse
    {

        $manoobra = $request->query->get('texto');

        $presupuesto->setManoobraPe($manoobra);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();
        $response = new JsonResponse();
                                                                                            
        return $response->setData("OK");
    }

    /**
     * @Route("/{id}", name="presupuestos_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Presupuestos $presupuesto): Response
    {
        if ($this->isCsrfTokenValid('delete'.$presupuesto->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($presupuesto);
            $entityManager->flush();
        }

        return $this->redirectToRoute('presupuestos_index');
    }


    /**
     * @Route("/ticketpdf/imprimir", name="presupuestopdf")
     */
    public function imprimir(Request $request): JsonResponse
    {
        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        // valores de data ajax
        $idpresu   = $request->query->get('id');

        // recuperar presupuesto
        $presupuesto = $em->getRepository(Presupuestos::class)->findOneBy(array('id' => $idpresu));
        

        // Configure Dompdf según sus necesidades
        $pdfOptions = new Options();
        $pdfOptions->set(['defaultFont'=>'orkneyregular', 
                          'defaultPaperSize'=>'A4',
                          'defaultPaperOrientation'=>'portrait',
                          'isHtml5ParserEnabled'=>'true'], );

        
        //Instantiate Dompdf with our options
        //$dompdf = new DOMPDF($pdfOptions);
        $dompdf = new Dompdf($pdfOptions);

        // Retrieve the HTML generated in our twig file
        $html= $this->renderView('presupuestobase.html.twig', [
            'title' => "Impresión ticket",
            'presupuesto' => $presupuesto,
            'cestaId' => $presupuesto->getTicket()->getId(),
             ]);
        
      // return $this->render('presupuestobase.html.twig', [
      //         'title' => "Impresión ticket",
      //         'presupuesto' => $presupuesto,
      //        'tipopago' => $tipopago,
      //         'cestaId' => $presupuesto->getTicket()->getId(),
      //          ]);
//
        // Cargar HTML en Dompdf
        $dompdf->loadHtml($html);
    
        // Renderiza el HTML como PDF
        $dompdf->render();

        // Almacenar datos binarios PDF
        $output = $dompdf->output();
        setlocale(LC_ALL, 'es_ES');   
        $namepdf = strftime("%y%j%H%M%S");              
        // En este caso, queremos escribir el archivo en el directorio público.
        $publicDirectory =  $this->getParameter("presupuestoDir") . '/' . $presupuesto->getClientePe()->getNombreCl() . ' ' . $presupuesto->getFechainiPe()->format('Y-m-d') .'/presupuestos';


        if (!file_exists($publicDirectory)) {
            mkdir($publicDirectory , 0777, true);
        }

        // e.g /var/www/project/public/mypdf.pdf
        $pdfFilepath =  $publicDirectory . '/'. $namepdf . '.pdf';

        // Escriba el archivo en la ruta deseada
        file_put_contents($pdfFilepath, $output);
        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['namepdf' => $publicDirectory . '/'. $namepdf . '.pdf']);
        
       
    }

    /**
     * @Route("/senalpdf/{id}/crear", name="senalpdf")
     */
    public function senalpdf(Request $request, Presupuestos $presupuesto, EstadocestasRepository $estadocestasRepository): JsonResponse
    {
        $tipopago  = $request->query->get('tipopago');
        $importe   = $request->query->get('importe');
        $estado    = $request->query->get('estado');


        $entityManager = $this->getDoctrine()->getManager();
        $estadocesta = $estadocestasRepository->findOneBy(
            ['id' => $estado],
        );

        $presupuesto->setEstadoPe($estadocesta);
        $presupuesto->setImporteSnalPe($importe);
        $presupuesto->setTipopagoSnalPe($tipopago);

        $entityManager->flush();

        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['cestaid' =>$presupuesto->getTicket()->getId()]);
        
       
    }



}
