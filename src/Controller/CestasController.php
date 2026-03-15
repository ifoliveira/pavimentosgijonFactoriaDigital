<?php

namespace App\Controller;

use App\Service\CestaUserService;
use App\Entity\Cestas;
use App\Entity\Detallecesta;
use App\Entity\Efectivo;
use App\Entity\Pagos;
use App\Form\CestasType;
use App\Repository\CestasRepository;
use App\Repository\EstadocestasRepository;
use App\Repository\BancoRepository;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use App\Service\TicketItem;
use App\Repository\TiposmovimientoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PagoService;
// Incluir los espacios de nombres requeridos por Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('admin/cestas')]
class CestasController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $em,
        private PagoService $pagoService,
    ) {}

    #[Route('/', name: 'cestas_index', methods: ['GET'])]
    public function index(CestasRepository $cestasRepository): Response
    {
        $ticketspendientes = $cestasRepository->findPendientesCobro();

        return $this->render('cestas/index.html.twig', [
            'cestas' => $cestasRepository->findFinalizados(),
            'cestasnal'=> $ticketspendientes,
        ]);
    }

    #[Route('/new', name: 'cestas_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $cesta = new Cestas();
        $form = $this->createForm(CestasType::class, $cesta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($cesta);
            $this->em->flush();

            return $this->redirectToRoute('cestas_index');
        }

        return $this->render('cestas/new.html.twig', [
            'cesta' => $cesta,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'cestas_show', methods: ['GET'])]
    public function show(Cestas $cesta): Response
    {
        return $this->render('cestas/show.html.twig', [
            'cesta' => $cesta,
        ]);
    }

    #[Route('/{id}/edit', name: 'cestas_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Cestas $cesta): Response
    {
        $form = $this->createForm(CestasType::class, $cesta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('cestas_index');
        }

        return $this->render('cestas/edit.html.twig', [
            'cesta' => $cesta,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'cestas_delete', methods: ['DELETE'])]
    public function delete(Request $request, Cestas $cesta): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cesta->getId(), $request->request->get('_token'))) {

            $this->em->remove($cesta);
            $this->em->flush();
        }

        return $this->redirectToRoute('cestas_index');
    }

    #[Route('/delete/fila', name: 'cesta_delete_ajax', methods: ['POST'])]
    public function deleteajax(Request $request): JsonResponse
    {
        $id    = $request->request->get('id');
        $cesta = $this->em->getRepository(Cestas::class)->find($id);

        if (!$cesta) {
            return new JsonResponse(['error' => 'No encontrado'], 404);
        }

        $this->em->remove($cesta);
        $this->em->flush();

        return new JsonResponse();
    }

   #[Route('/ticketpdf/{id}/epson', name: 'impepson', methods: ['GET','POST'])]
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
        array_push($items, new TicketItem($detallecesta->getCantidadDc(), $detallecesta->getproductoDc()->getdescripcionPd() ,number_format(($detallecesta->getCantidadDc() * $detallecesta->getPvpDc()),2,',','.')));
            $totaln = $totaln + ($detallecesta->getCantidadDc() * $detallecesta->getPvpDc());
        };

        $senal = new TicketItem('','Senal', number_format(($importesnal*-1),2,',','.'));

        
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
        $printer -> text(new TicketItem('', '', '€'));
        $printer -> setEmphasis(false);
        foreach ($items as $item) {
            $printer -> text($item);
        }

        If ($cierre == 'SN') {
            $adelantowrite = new TicketItem('','Adelanto', number_format($adelanto * -1,2,',','.') );
            $printer -> text($adelantowrite );
            $totaln = $totaln - $adelanto ;
            $importesnal = $adelanto;
        }

        if ($importesnal != 0 and $cierre != 'SN') {

            
            $totaln = $totaln - $importesnal;
            $total = new TicketItem('','Pendiente', number_format($totaln,2,',','.'), true);

            $printer -> setEmphasis(true);
            $printer -> text($senal);
            $printer -> setEmphasis(false);

        } else {

            $total = new TicketItem('','Total', number_format($totaln,2,',','.'), true);

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
    #[Route('/ticket/{id}/detalle', name: 'ticketdet', methods: ['GET','POST'])]
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
    #[Route('/ticketpdf/{id}/imprimir', name: 'ticketpdf', methods: ['GET','POST'])]
    public function imprimir(Request $request, Cestas $cesta): jsonResponse
    {

        // get EntityManager
       
        $cestauser = new CestaUser($this->em);

 
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

    #[Route('/{id}/tipopago', name: 'cesta_tipopago', methods: ['POST'])]
    public function cestatipopago(Cestas $cesta): Response
    {
        $cesta->setTipopagoCs(
            $cesta->getTipopagoCs() === 'Tarjeta' ? 'Efectivo' : 'Tarjeta'
        );

        $this->em->flush();

        return $this->redirectToRoute('cestas_index');
    }


    #[Route('/{id}/finalizar', name: 'cestas_finalizar')]
    public function finalizar(Request $request, Cestas $cestas, TiposmovimientoRepository $tiposmovimientoRepository): Response
    {   

        $tipopago = $request->query->get('tipopago');
        $numticket = $request->query->get('numticket'); 
        $importetot = $request->query->get('importe'); 
        $importesnal = $request->query->get('importesnal'); 
        $pagado = $importesnal;

     // Cambiamos el estado de la cesta del presupuesto para que sea actual

        // Si el importe abonado (señal) es 0 el ticket no pendiente 
        if ($cestas->getEstadoCs() == 1) {
            if ($importesnal == 0) {
                $cestas->setEstadoCs(2);
                $cestas->setFechaFinCs(new \DateTime('now', new \DateTimeZone('Europe/Madrid')));
            } else {
                $cestas->setEstadoCs(3);
            }

            $cestas->setImporteTotCs($importetot);
            $cestas->setTipopagoCs($tipopago);
            $cestas->setNumticketCs($numticket);
            $cestas->setFechaCs(new \DateTime('now', new \DateTimeZone('Europe/Madrid')));


        } else {

            foreach ($cestas->getPagos() as &$valor) {
                $pagado = $pagado + $valor->getImportePg();
            }

            if ($pagado == $cestas->getImporteTotCs()) {
                $cestas->setEstadoCs(2);
                $cestas->setFechaFinCs(new \DateTime('now', new \DateTimeZone('Europe/Madrid')));
            } 
        }    
        
        $cestas->setTimestampCs(new \DateTime());
        $this->em->persist($cestas);

        // Creamos un pago con lo que llegue en la señal


        if ($importesnal == 0) {  
            $importe = $importetot;
        } else {
            $importe = $importesnal;
        }        

        $this->pagoService->ticketPagoFinal($cestas, $importe, $tipopago, $tiposmovimientoRepository);
        
        $this->em->flush();  

        $response = new JsonResponse();

        // Envía una respuesta de texto
        return $response->setData("OK");
    }



    #[Route('/{id}/deletelogico', name: 'delete_logico')]
    public function deletelogico(Cestas $cestas): Response
    {
        foreach ($cestas->getDetallecesta() as $detallecesta) {
            $this->em->remove($detallecesta);
        }

        $this->em->flush();

        return new JsonResponse("OK");
    }


    #[Route('/conciliar/{id}', name: 'conciliar_cesta', methods: ['GET'])]
    public function conciliar(Cestas $cesta, BancoRepository $bancoRepository): Response
    {
   
        return $this->render('cestas/conciliar.html.twig', [
            'cesta' => $cesta,
            'bancos' => $bancoRepository->findAll(),
        ]);
    }    
    #[Route('/{id}/{idbanco}/conciliar', name: 'cestas_conciliar', methods: ['GET','POST'])]
    public function conciliar_banco(Cestas $cesta, BancoRepository $bancoRepository, int $idbanco): Response
    {
        
        $banco = $bancoRepository->findOneBy(array('id' => $idbanco));

        if ($cesta->getImporteTotCs() != $banco->getImporteBn()){

            $banconuevo = clone $banco;  
            $banconuevo->setImporteBn($cesta->getImporteTotCs());
            $importenuevo= $banco->getImporteBn() - $cesta->getImporteTotCs();
            $banco->setImporteBn($importenuevo);

            $this->em->persist($banconuevo);

        }

          

        $this->em->flush();

        return $this->redirectToRoute('cestas_index');
    }

 


}
