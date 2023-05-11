<?php

namespace App\Controller;

use App\Entity\Presupuestos;
use App\Entity\Productos;
use App\Entity\manoObra;
use App\Entity\Efectivo;
use App\Entity\Economicpresu;

use App\Form\PresupuestosType;
use App\Form\CollectionType;
use App\Form\PresupuestosManoObraType;
use App\Form\ProductosType;
use App\Entity\Cestas;
use App\MisClases\EconomicoPresu;
use App\MisClases\CestaUser;
use App\Repository\EfectivoRepository;
use App\Repository\EstadocestasRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\CestasRepository;
use App\Repository\ProductosRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\EconomicpresuRepository;
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
    public function new(Request $request, EstadocestasRepository $estadocestasRepository): Response
    {
        $presupuesto = new Presupuestos();
        $form = $this->createForm(PresupuestosType::class, $presupuesto);
        $form->handleRequest($request);

        $estadocesta=$estadocestasRepository->findOneBy(['id' => 6]);
        $presupuesto->setEstadoPe($estadocesta);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $user = $this->getUser();
            $presupuesto->setUserPe($user);
            $cesta = new Cestas();
            $cesta->setUserCs($user->getId());
            $cesta->setEstadoCs(11);
            $entityManager->persist($cesta);
            $presupuesto->setTicket($cesta);            
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
    public function show(Request $request, Presupuestos $presupuesto, EfectivoRepository $efectivoRepository, ProductosRepository $productosRepository, EconomicpresuRepository $economicpresu, CestasRepository $cestasRepository): Response
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
            case $presupuesto->getEstadoPe()->getId() >= 6:
                $productos = $productosRepository->findAll();
                  break;
            default:
                $productos = new Productos;
        }

        $economic = $presupuesto->getEconomicpresus();

        $cestas = $cestasRepository->findBy(array('prespuestoCs' => $presupuesto->getId(), 'estadoCs' => '2'));
        $efectivos = $efectivoRepository->findBy(array('presupuestoef' => $presupuesto->getId()));

        
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
            'economic' => $economic,
            'form' => $form->createView(),
            'formprod' => $formprod->createView(),
            'formestado' => $formestado->createView(),
            'formmanoob' => $formmanoob->createView(),
            'formestadopr' => $formestadopr->createView(),
            'fotos' => $ficheros,
            'cestaId'=> $presupuesto->getTicket()->getId(),
            'productos' => $productos,
            'cestas' => $cestas,
            'efectivos' => $efectivos,
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
    public function editestado(Request $request, Presupuestos $presupuesto, int $estado, EstadocestasRepository $estadocestasRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        $formestado = $this->createForm(PresupuestosType::class, $presupuesto);
        $formestado->handleRequest($request);

        if ($formestado->isSubmitted() && $formestado->isValid()) {
        
            $estadocesta = $estadocestasRepository->findOneBy(
                ['id' => $estado],
            );

            if ($estado == 7 ) {

                $presupuesto->setImportetotPe($detallecestaRepository->imptotalCesta($presupuesto->getTicket()->getId()));
            }

            $presupuesto->setEstadoPe($estadocesta);
            $entityManager = $this->getDoctrine()->getManager();

            $entityManager->flush();


            }

                                                                                            
        return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId() ));
    }

    /**
     * @Route("/{id}/modificar", name="presupuestos_modificar")
     */
    public function modificar(Request $request, Presupuestos $presupuesto, ProductosRepository $productosRepository, EconomicpresuRepository $economicpresu): Response
    {
        $form = $this->createForm(PresupuestosType::class, $presupuesto);
        $form->handleRequest($request);

        $productos = $productosRepository->findAll();

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('presupuestos_index');
        }

        return $this->render('presupuestos/modificar.html.twig', [
            'presupuesto' => $presupuesto,
            'productos' => $productos,
            'economic' => $presupuesto->getEconomicpresus(),
            'cestaId'=> $presupuesto->getTicket()->getId(),
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/{id}/finalizar", name="presupuestos_finalizar")
     */
    public function finalizar(Request $request, Presupuestos $presupuesto, EstadocestasRepository $estadocestasRepository): Response
    {   

        $entityManager = $this->getDoctrine()->getManager();
       
        $estadocesta = $estadocestasRepository->findOneBy(
            ['id' => 10],
        );

        $presupuesto->setEstadoPe($estadocesta);
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
        $entityManager = $this->getDoctrine()->getManager();

        $entityManager->flush();

                                                                                            
        return $this->redirectToRoute('presupuestos_show', array('id' => $presupuesto->getId()));
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
     * @Route("/{id}/{precios}/{tipo}/generar", name="presupuestos_generar", methods={"GET","POST"})
     */
    public function generar(Request $request, string $precios, Presupuestos $presupuesto, string $tipo): Response
    {

        return $this->render('presupuestos/generar.html.twig', [
            'presupuesto' => $presupuesto,
            'precios' => $precios,
            'tipo' => $tipo
        ]);
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
        $publicDirectory = $presupuesto->getClientePe()->getNombreCl() . ' ' . $presupuesto->getFechainiPe()->format('Y-m-d') .'/presupuestos';


        if (!file_exists($publicDirectory)) {
            mkdir($publicDirectory , 0777, true);
        }

        // e.g /var/www/project/public/mypdf.pdf
        $pdfFilepath =  $this->getParameter("presupuestoDir") . '/' . $publicDirectory . '/'. $namepdf . '.pdf';

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

    /**
     * @Route("/aceptarpresu/{id}", name="aceptarpresu")
     */
    public function aceptarpresu(Request $request, Presupuestos $presupuesto, DetallecestaRepository $detallecestaRepository, EstadocestasRepository $estadocestasRepository): JsonResponse
    {
        $tipopago  = $request->query->get('tipopago');
        $importesenal   = $request->query->get('importesenal');
        $entityManager = $this->getDoctrine()->getManager();
        $estadocesta = $estadocestasRepository->findOneBy(
            ['descripcionEc' => 'Aceptado'],
        );

        $presupuesto->setImporteSnalPe($importesenal);
        $presupuesto->setTipopagoSnalPe($tipopago);
        $presupuesto->setEstadoPe($estadocesta);
        
        $cestanueva = new Cestas();
        $cestanueva = clone $presupuesto->getTicket();        


        $presupuesto->getTicket()->setPrespuestoCs($presupuesto);
        $presupuesto->getTicket()->setImportePagoCs($importesenal);
        $presupuesto->getTicket()->setImporteTotCs($importesenal);
        $presupuesto->getTicket()->setDescuentoCs($detallecestaRepository->imptotalCesta($presupuesto->getTicket())-$importesenal);
        

        $cestanueva->setPrespuestoCs($presupuesto);
        $cestanueva->setEstadoCs(2);
        $cestanueva->setImportePagoCs($importesenal);
        $cestanueva->setImporteTotCs($importesenal);
        $cestanueva->setTipopagoCs($tipopago);

 
        $entityManager->persist($cestanueva);
        $entityManager->flush();

        $economic = new EconomicoPresu($entityManager);

        $economic->iniciarPresu($presupuesto->getimportemanoobra(),$presupuesto->getTicket()->getDescuentoCs(),$presupuesto);

        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['respuesta' =>'presupuesto actualizado']);
        
       
    }

    /**
     * @Route("/cobromaterialpresu/{id}", name="cobrarpresu")
     */
    public function cobrarpresu(Request $request, Presupuestos $presupuesto, DetallecestaRepository $detallecestaRepository, EstadocestasRepository $estadocestasRepository): JsonResponse
    {
        $tipopago = $request->query->get('tipopago');
        $importesenal = $request->query->get('importesenal');
        $ideconomic = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();
        $actualizar = $entityManager->getRepository('App\Entity\Economicpresu')->findOneBy(['id'=> $ideconomic]);
       // $actualizar->setImporteEco($actualizar->getImporteEco()-$importesenal);

        if (($actualizar->getImporteEco()-$importesenal) == 0) {
            $actualizar->setImporteEco($importesenal);
            $actualizar->setEstadoEco(6);    
        } else {
            $actualizar->setImporteEco($actualizar->getImporteEco() - ($importesenal));
            $economicnuevo = new economicpresu();
            $economicnuevo = clone $actualizar;
            $economicnuevo->setEstadoEco(6);  
            $economicnuevo->setImporteEco($importesenal);  
            $entityManager->persist($economicnuevo);                   
        };        

        $presupuesto->setImporteSnalPe($importesenal + $presupuesto->getImporteSnalPe());

        $cestanueva = new Cestas();
        $cestanueva = clone $presupuesto->getTicket();
        $cestanueva->setImportePagoCs($importesenal);
        $cestanueva->setEstadoCs(2);
        $cestanueva->setImporteTotCs($importesenal);
        $cestanueva->setTipopagoCs($tipopago);
        $cestanueva->setDescuentoCs(0);
        $cestanueva->setTimestampCs(new \DateTime());

        $entityManager->persist($cestanueva);
        $entityManager->flush();

        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['respuesta' =>'presupuesto actualizado']);
        
       
    }    

   /**
     * @Route("/cobromanopresu/{id}", name="cobrarmanopresu")
     */
    public function cobrarmanopresu(Request $request, Presupuestos $presupuesto, DetallecestaRepository $detallecestaRepository, EstadocestasRepository $estadocestasRepository): JsonResponse
    {
        $tipopago = $request->query->get('tipopago');
        $importe = $request->query->get('importe');
        $ideconomic = $request->query->get('id');

        $entityManager = $this->getDoctrine()->getManager();
        $actualizar = $entityManager->getRepository('App\Entity\Economicpresu')->findOneBy(['id'=> $ideconomic]);
        //$actualizar->setImporteEco($actualizar->getImporteEco()-$importe);

        if (($actualizar->getImporteEco()-$importe) == 0) {
            $actualizar->setImporteEco($importe);
            if ($tipopago == "Efectivo") {           
            $actualizar->setEstadoEco(6); 
            } else{
                $actualizar->setEstadoEco(7); 
            }   

        } else {
            $actualizar->setImporteEco($actualizar->getImporteEco() - ($importe));
            $economicnuevo = new economicpresu();
            $economicnuevo = clone $actualizar;
            if ($tipopago == "Efectivo") {                       
                $economicnuevo->setEstadoEco(6);  
            } else{
                $economicnuevo->setEstadoEco(7); 
            }                   
            $economicnuevo->setImporteEco($importe);  
            $entityManager->persist($economicnuevo);                   
        };        

        if ($tipopago == "Efectivo") {
        // Generamos movimiento efectivo
            $efectivo = new Efectivo();
            $efectivo->setTipoEf($entityManager->getRepository('App\Entity\Tiposmovimiento')->findOneBy(['descripcionTm'=> 'Mano de Obra']));
            $efectivo->setImporteEf($importe);
            $efectivo->setFechaEf(new \DateTime());
            $efectivo->setConceptoEf($actualizar->getConceptoEco() . ' ' . $actualizar->getIdpresuEco()->getClientePe()->getDireccionCl());
            $efectivo->setPresupuestoef($actualizar->getIdpresuEco());
            $entityManager->persist($efectivo );
            
        }    
        $entityManager->flush();
        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['respuesta' =>'presupuesto actualizado']);
        
       
    }        

    /**
     * @Route("/modificarpresu/{id}", name="modificarpresu")
     */
    public function modificarpresu(Request $request, Presupuestos $presupuesto, DetallecestaRepository $detallecestaRepository, EstadocestasRepository $estadocestasRepository): JsonResponse
    {
        $tipopago  = $request->query->get('tipopago');
        $importesenal   = $request->query->get('importesenal');
        $entityManager = $this->getDoctrine()->getManager();
        $estadocesta = $estadocestasRepository->findOneBy(
            ['descripcionEc' => 'Aceptado'],
        );

        $presupuesto->setImporteSnalPe($importesenal);
        $presupuesto->setTipopagoSnalPe($tipopago);
        $presupuesto->setEstadoPe($estadocesta);
        $presupuesto->getTicket()->setPrespuestoCs($presupuesto);
        $presupuesto->getTicket()->setEstadoCs(2);
        $presupuesto->getTicket()->setImportePagoCs($importesenal);
        $presupuesto->getTicket()->setImporteTotCs($importesenal);
        $presupuesto->getTicket()->setDescuentoCs($detallecestaRepository->imptotalCesta($presupuesto->getTicket())-$importesenal);

        $entityManager->flush();

        $economic = new EconomicoPresu($entityManager);
        $economic->actualizaResto($detallecestaRepository->imptotalCesta($presupuesto->getTicket())-$importesenal,$presupuesto);
        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData(['respuesta' =>'presupuesto actualizado']);
        
       
    }

        /**
     * @Route("/delete/fila", name="presupuesto_delete_ajax", methods={"GET","POST"})
     */
    public function deletepresuajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del presupuesto
        $datos = $request->query->get('id');
        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        // Obtener presupuesto
        $presupuesto = $em->getRepository('App\Entity\Presupuestos')->find($datos);

        // Borrado del detalle
        $em->remove($presupuesto);
        $em->flush();

        $response = new JsonResponse();

        return $response;

    } 

 /**
     * @Route("/actualiza/importe", name="presuactualiza_imp", methods={"GET","POST"})
     */
    public function ajaxinscS(Request $request): jsonResponse
    {

        // Funcion encargada de añadir producto a la cesta
        $entityManager = $this->getDoctrine()->getManager();
        $cestauser = new CestaUser($entityManager);
        // Obtener ID del presupuesto
        $datos = $request->query->get('id');
        // Obtener presupuesto
        $presupuesto = $entityManager->getRepository('App\Entity\Presupuestos')->find($datos);
        // Producto y cantidad a añadir
        $importe = $cestauser->getImporteTot($presupuesto->getTicket()->getId());

        $presupuesto->setImportetotPe($importe);

        $entityManager->flush();

        $response = new JsonResponse();

        return $response;


    }      


}
