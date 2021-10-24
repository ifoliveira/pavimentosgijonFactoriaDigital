<?php

namespace App\Controller;

use App\MisClases\CestaUser;
use App\Entity\Cestas;
use App\Entity\Detallecesta;
use App\Form\CestasType;
use App\Repository\CestasRepository;
use App\Repository\EstadocestasRepository;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use App\MisClases\item;
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
            'cestasnal'=> $cestasRepository->ticketssnal(),
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

    /**
     * @Route("/delete/fila", name="cesta_delete_ajax", methods={"GET","POST"})
     */
    public function deleteajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');
        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        // Obtener cesta
        $cesta = $em->getRepository('App\Entity\Cestas')->find($datos);

        // Borrado del detalle
        $em->remove($cesta);
        $em->flush();

        $response = new JsonResponse();

        return $response;



    }  

   /**
     * @Route("/ticketpdf/{id}/epson", name="impepson", methods={"GET","POST"})
     */
    public function epsonimp(Request $request, Cestas $cesta): jsonResponse
    {
   
    /* Fill in your own connector here */

	// Enter the share name for your USB printer here
	//$connector = new WindowsPrintConnector("EPSONTicket");

        $connector = new FilePrintConnector("/dev/usb/lp1");
        $items = array();
        $totaln = 0;
        $tipopago = $request->query->get('tipopago');
        $importesnal = $request->query->get('importesnal');
        $id = $cesta->getId();
        $cierre = $request->query->get('espresu');
        $adelanto = $cesta->getDescuentoCs();

        foreach ($cesta->getdetallecesta() as $detallecesta) {
        array_push($items, new item($detallecesta->getCantidadDc(), $detallecesta->getproductoDc()->getdescripcionPd() ,number_format(($detallecesta->getCantidadDc() * $detallecesta->getPvpDc()),2,',','.')));
            $totaln = $totaln + ($detallecesta->getCantidadDc() * $detallecesta->getPvpDc());
        };

        $senal = new item('','Senal', number_format(($importesnal*-1),2,',','.'));

        
        /* Date is kept the same for testing */
        $date = date('l jS \of F Y h:i:s A');
        //$date = "Monday 6th of April 2015 02:56:25 PM";
        
        /* Start the printer */
        $logo = EscposImage::load("../vendor/mike42/escpos-php/example/resources/Logo-Pavimentos-Gijon-BN.png", false);
        $printer = new Printer($connector);
        
        /* Print top logo */
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> graphics($logo);
        
        /* Name of shop */
        $printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
        $printer -> text("Pavimentos Gijón\n");
        $printer -> selectPrintMode();
        $printer -> text("53543499M Avenida Schultz Nº 28 Bajo\n");
        $printer -> text("985-391-326\n");

        $printer -> feed();
        
        /* Title of receipt */
        $printer -> setEmphasis(true);
        $printer -> text("FACTURA SIMPLIFICADA " . $tipopago . "\n");
        $printer -> setEmphasis(false);
        
        /* RESERVA */
        if ($importesnal != 0 and $cierre != 'SN') {

            $printer -> setEmphasis(true);
            $printer -> text("*** RESERVA *** " . $id .  "\n");
            $printer -> setEmphasis(false);
            $printer -> feed();
        
        }
        /* Items */
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> setEmphasis(true);
        $printer -> text(new item('', '', '€'));
        $printer -> setEmphasis(false);
        foreach ($items as $item) {
            $printer -> text($item);
        }

        If ($cierre == 'SN') {
            $adelantowrite = new item('','Adelanto', number_format($adelanto * -1,2,',','.') );
            $printer -> text($adelantowrite );
            $totaln = $totaln - $adelanto ;
            $importesnal = $adelanto;
        }

        if ($importesnal != 0 and $cierre != 'SN') {

            
            $totaln = $totaln - $importesnal;
            $total = new item('','Pendiente', number_format($totaln,2,',','.'), true);

            $printer -> setEmphasis(true);
            $printer -> text($senal);
            $printer -> setEmphasis(false);

        } else {

            $total = new item('','Total', number_format($totaln,2,',','.'), true);

        }
        /*$printer -> setEmphasis(true);
        $printer -> text($subtotal);
        $printer -> setEmphasis(false);
        $printer -> feed();*/
        
        /* Tax and total */
        /*$printer -> text($tax);*/
        $printer -> feed();
        $printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
        $printer -> text($total);
        $printer -> selectPrintMode();
        
        /* Footer */
        $printer -> feed(2);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> text("GRACIAS POR SU COMPRA\n");
        $printer -> text("Visitanos en pavimentosgijon.es\n");
        $printer -> feed(2);
        $printer -> text($date . "\n");
        
        /* Cut the receipt and open the cash drawer */
        $printer -> cut();
        $printer -> pulse();
        
        $printer -> close();
        $response = new JsonResponse();
    
        If ($cierre == 'SN') {
            $importeres= $totaln;
        }else{
            $importeres= $totaln + floatval($importesnal);
        }

        // Envía una respuesta de texto
        return $response->setData(['namepdf' => 'ImpresoraTk', 'importe' => $importeres, 'importesnal' => $importesnal] );
      
      
    }

    //Obtener array de detalle de ticket
    /**
     * @Route("/ticket/{id}/detalle", name="ticketdet", methods={"GET","POST"})
     */
    public function ticketdet(Request $request, Cestas $cesta): jsonResponse
    {
        $salida = array();
        $response = new JsonResponse();
        foreach ($cesta->getdetallecesta() as $detalles) {
            array_push($salida, array ("id" => $detalles->getId(), 
                                       "producto" => $detalles->getProductoDc()->getDescripcionPd(), 
                                       "cantidad" => $detalles->getCantidadDc(), 
                                       "pvp"      => $detalles->getPvpDc(), 
                                       "descuento" => $detalles->getDescuentoDc()));
        
        }

        // Envía una respuesta de texto
        return $response->setData($salida);
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
        $importesnal = $request->query->get('importesnal');
        //$importesnal = ('SI' == $request->query->get('espresu')) ? $request->query->get('importesnal') : 0;
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
        return $response->setData(['namepdf' => $namepdf.'.pdf', 'importe' => $importe, 'importesnal' => $importesnal] );
            // display the file contents in the browser instead of downloading it
        //return $this->file($pdfFilepath , 'my_invoice.pdf', ResponseHeaderBag::DISPOSITION_INLINE);
        
       
    }

    /**
     * @Route("/{id}/senal", name="cesta_senal", methods={"GET","POST"})
     */
    public function cestasenal(Request $request, Cestas $cesta, CestasRepository $cestasRepository): Response
    {
 
        // Funcion encargada de duplicar la cesta en cuanto a productos para cerrar compra con señal
        $entityManager = $this->getDoctrine()->getManager();

        
        // Creamos objeto detalle de cesta, con el usuario conectado y los metodos de CestaUser
        $user = $this->getUser();
        $tienecesta = $cestasRepository->findBy(
            ['userCs' => $user->getId(),
            'estadoCs' => '1'],
        );


        $detalles = $cesta->getdetallecesta();
        foreach ($cesta->getdetallecesta() as $detalles){
           
            $detcesta = new Detallecesta;
            $detcesta->setCestaDc($tienecesta[0]);
            $detcesta->setproductoDc($detalles->getproductoDc());
            $detcesta->setCantidadDc($detalles->getCantidadDc());
            $detcesta->setPrecioDc($detalles->getPrecioDc());
            $detcesta->setpvpDc($detalles->getpvpDc()); 
            $detcesta->setTextoDc($detalles->getTextoDc()); 
            
            // Insertamos en la tabla el detalle
            $entityManager->persist($detcesta);
            
         }

         $cesta->setEstadoCs(2);
         $entityManager->persist($cesta);

         $tienecesta[0]->setImporteTotCs($cesta->getDescuentoCs());
         $tienecesta[0]->setDescuentoCs($cesta->getImporteTotCs());
         $entityManager->persist($tienecesta[0]);


         $entityManager->flush();
         return $this->redirectToRoute('cestas_show', array ('id' => $tienecesta[0]->getId()));

    }  

    /**
     * @Route("/{id}/tipopago", name="cesta_tipopago", methods={"GET","POST"})
     */
    public function cestatipopago(Request $request, Cestas $cesta,CestasRepository $cestasRepository): Response
    {
 
        // Funcion encargada de invertir el tipo de pago
        $entityManager = $this->getDoctrine()->getManager();

        if($cesta->getTipopagoCs() == 'Tarjeta') {

            $cesta->setTipopagoCs('Efectivo');
        }else{

            $cesta->setTipopagoCs('Tarjeta');
        }

         $entityManager->persist($cesta);
         $entityManager->flush();

         return $this->redirectToRoute('cestas_index');

    }  


    /**
     * @Route("/{id}/finalizar", name="cestas_finalizar")
     */
    public function finalizar(Request $request, Cestas $cestas, CestasRepository $cestasRepository,EstadocestasRepository $estadocestasRepository): Response
    {   

        $tipopago = $request->query->get('tipopago');
        $numticket = $request->query->get('numticket'); 
        $importetot = $request->query->get('importe'); 
        $importesnal = $request->query->get('importesnal'); 
     
        // Cambiamos el estado de la cesta del presupuesto para que sea actual

        $entityManager = $this->getDoctrine()->getManager();

        $cestas->setEstadoCs(2);
        $cestas->setImporteTotCs($importetot);
        if (floatval($importesnal) != 0) {
            $cestas->setImporteTotCs($importesnal);
            $cestas->setDescuentoCs($importetot-$importesnal);
            $cestas->setEstadoCs(9);
        }

        $cestas->setTipopagoCs($tipopago);
        $cestas->setNumticketCs($numticket);
        $cestas->setFechaCs(new \DateTime());
        $cestas->setTimestampCs(new \DateTime());
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
