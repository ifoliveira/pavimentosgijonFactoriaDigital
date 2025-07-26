<?php

namespace App\Controller;

use App\Entity\Consultas;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Form\LogsType;
use App\Entity\Logs;
use App\Form\ConsultasType;
use App\Repository\ConsultasRepository;
use App\MisClases\OpenAIClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\MisClases\TelegramNotifier;
use Dompdf\Dompdf; 

class ApgijonController extends AbstractController
{

    private Dompdf $pdf;

    private array $precios;

    public function __construct(ParameterBagInterface $params)
    {
        $this->precios = $params->get('presupuesto');
        $this->pdf = new Dompdf();
        $this->pdf->setPaper('A4', 'portrait');
        $this->pdf->set_option('isHtml5ParserEnabled', true);
    }

        #[Route('/api/presupuesto/calculate', name: 'api_presupuesto_calculate', methods: ['POST'])]
        public function calcular(Request $request, TelegramNotifier $notifier): JsonResponse
        {
            $datos = json_decode($request->getContent(), true);

            if (!$datos || !isset($datos['tipo_reforma'])) {
                return $this->json(['error' => 'Datos incompletos o inválidos.'], 400);
            }

            $tipo = $datos['tipo_reforma'];
            $resultados = [];

            switch ($tipo) {
                case 'cambio_bañera_por_plato_ducha':
                    $resultados = $this->calcularPresupuestoDucha($datos);
                    break;

                case 'baño_completo':
                    $resultados = $this->calcularPresupuestoBanioCompleto($datos);
                    break;

                default:
                    return $this->json(['error' => 'Tipo de reforma no soportado.'], 400);
            }

            // $notifier->sendMessage(...) si quieres notificar
            return $this->json($resultados);
        }

private function calcularPresupuestoBanioCompleto(array $datos): array
{
    $p = $this->precios;
    $manoObra = 0;
    $materiales = 0;

    $largo = $datos['medidas_bano']['largo_m'];
    $ancho = $datos['medidas_bano']['ancho_m'];
    $alto = $datos['medidas_bano']['alto_m'];

    $m2_paredes = 2 * ($largo + $ancho) * $alto;
    $m2_suelo = $largo * $ancho;

    // 🧱 MANO DE OBRA
    $manoObra += $p['mano_obra']['albañil']['reforma_completa'];
    // Calcular puntos de agua
    $puntosAgua = 0;

    // Siempre hay inodoro
    $puntosAgua++;

    // Mueble lavabo
    if (!empty($datos['mueble_lavabo']['ancho_cm'])) {
        $puntosAgua++;
    }

    // Plato de ducha o bañera
    if (!empty($datos['sanitarios']['ducha_o_banera']['tipo'])) {
        $puntosAgua++;
    }

    // Grifo (ya viene con ducha o bañera, pero si instalas barra puede contar aparte)
    if (!empty($datos['griferia']['instalar_barra_ducha'])) {
        $puntosAgua++;
    }

    // Bidé si no se suprime
    if (
        !empty($datos['sanitarios']['bide']['hay_bide_actual']) &&
        empty($datos['sanitarios']['bide']['suprimir'])
    ) {
        $puntosAgua++;
    }

    // Llaves de corte si quieres contarlas como extra (puedes crear una bandera en el JSON)
    if (!empty($datos['fontaneria']['llaves_corte'])) {
        $puntosAgua++;
    }

    // Multiplicar
    $manoObra += $puntosAgua * $p['mano_obra']['fontanero_base'];
    $manoObra += $p['mano_obra']['pintura_techo'];
    $manoObra += $p['mano_obra']['electricista_base'];
    $manoObra += $p['mano_obra']['jefe_obra'];
    $manoObra += $p['mano_obra']['mampara_instalacion'];

    if (!empty($datos['griferia']['instalar_barra_ducha'])) {
        $manoObra += $p['mano_obra']['barra_ducha_extra'];
    }

    if (!empty($datos['techo']['reinstalar_escayola'])) {
        $manoObra += $p['mano_obra']['escayola_instalacion'];
    }

    if (!empty($datos['calefaccion']['sustituir_por_toallero'])) {
        $manoObra += $p['mano_obra']['radiador_toallero_instalacion'];
    }

    $manoObra = max($manoObra, $p['mano_obra']['minimo']);

    // 🧱 MATERIALES

    // Revestimientos
    $materiales += $m2_paredes * $p['materiales']['azulejos']['precio_m2'];
    $materiales += $m2_suelo * $p['materiales']['pavimento']['precio_m2'];

    // Cola específica para baño completo
    $materiales += $p['materiales']['cola']['bano_completo'];

    // Plato de ducha o bañera
    $materiales += $p['materiales']['sanitarios'][$datos['sanitarios']['ducha_o_banera']['tipo']] ?? 0;

    // Grifería
    $materiales += !empty($datos['griferia']['instalar_barra_ducha'])
        ? $p['materiales']['griferia']['barra_ducha']
        : $p['materiales']['griferia']['estandar'];

    // Mueble de lavabo
    $ancho = $datos['mueble_lavabo']['ancho_cm'] ?? null;

    $precioLavabo = match (true) {
        $ancho <= 60 => $p['materiales']['muebles']['lavabo'][60],
        $ancho <= 80 => $p['materiales']['muebles']['lavabo'][80],
        $ancho >= 100 => $p['materiales']['muebles']['lavabo'][100],
        default => $p['materiales']['muebles']['lavabo'][80] // el más grande por defecto
    };

    $materiales += $precioLavabo;

    // Espejo (siempre)
    $materiales += $p['materiales']['muebles']['espejo'];

    // Inodoro (siempre)
    $materiales += $p['materiales']['sanitarios']['inodoro'];

    // Foco LED (siempre)
    $materiales += $p['materiales']['electricidad']['foco'];

    // Radiador toallero (opcional)
    if (!empty($datos['calefaccion']['sustituir_por_toallero'])) {
        $materiales += $p['materiales']['radiador_toallero'];
    }

    // Escayola (opcional)
    if (!empty($datos['techo']['reinstalar_escayola'])) {
        $materiales += $p['materiales']['escayola'];
    }

    // Bidé (solo si hay y no se suprime)
    if (
        !empty($datos['sanitarios']['bide']['hay_bide_actual']) &&
        empty($datos['sanitarios']['bide']['suprimir'])
    ) {
        $materiales += $p['materiales']['sanitarios']['bide'];
        $materiales += $p['materiales']['griferia']['bide']; // grifo del bidé
    }

    $totalMin = $manoObra + $materiales;
    $totalMax = $totalMin + 100;

    return [
        'mano_obra' => round($manoObra),
        'materiales' => round($materiales),
        'total_estimado_min' => round($totalMin),
        'total_estimado_max' => round($totalMax),
    ];
}



    private function calcularPresupuestoDucha(array $datos): array
    {
        $p = $this->precios;
        $manoObra = 0;
        $materiales = 0;

        $altura = $datos['zona_azulejos']['altura_reforma'];
        $entreParedes = $datos['entre_paredes'];
        $longitud = $datos['medidas_bañera']['largo_cm'] / 100;

        // 🧱 Mano de obra
        $manoObra += match ($altura) {
            '1m' => $p['mano_obra']['albañil']['hasta_1m'],
            'techo' => $p['mano_obra']['albañil']['hasta_techo'],
            default => 0
        };

        $manoObra += $p['mano_obra']['fontanero_base'];

        if (!empty($datos['griferia']['instalar_barra_ducha'])) {
            $manoObra += $p['mano_obra']['barra_ducha_extra'];
        }

        if ($altura === 'techo') {
            $manoObra += $p['mano_obra']['pintura_techo'];
        }

        if ($datos['mampara']['tipo'] !== 'ninguna') {
            $manoObra += $p['mano_obra']['mampara_instalacion'];
        }

        $manoObra += $p['mano_obra']['jefe_obra'];

        $manoObra = max($manoObra, $p['mano_obra']['minimo']);

        // 🧱 Materiales

        if ($longitud <= 1.2) {
            $materiales += $p['materiales']['plato_ducha']['hasta_120'];
        } elseif ($longitud <= 1.6) {
            $materiales += $p['materiales']['plato_ducha']['hasta_160'];
        } else {
            $materiales += $p['materiales']['plato_ducha']['mayor'];
        }

        $materiales += $p['materiales']['mampara'][$datos['mampara']['tipo']] ?? 0;

        $materiales += !empty($datos['griferia']['instalar_barra_ducha'])
            ? $p['materiales']['griferia']['barra_ducha']
            : $p['materiales']['griferia']['estandar'];

        if (!empty($datos['zona_azulejos']['derribo'])) {
            $paredes = $entreParedes ? 2 : 1;
            $altura_m = $altura === 'techo' ? 2.4 : ($altura === '1m' ? 1 : 0.5);
            $m2 = $paredes * $longitud * $altura_m;
            $materiales += $m2 * $p['materiales']['azulejos']['precio_m2'];

            $materiales += $p['materiales']['cola']['base'];
            if ($altura === 'techo') {
                $materiales += $p['materiales']['cola']['extra_techo'];
            }
        }

        if ($datos['zona_azulejos']['reponer_azulejos'] === 'buscar_similar') {
            $materiales += $p['materiales']['azulejos_similares_extra'];
        }

        $totalMin = $manoObra + $materiales;
        $totalMax = $totalMin + 100;

        return [
            'mano_obra' => round($manoObra),
            'materiales' => round($materiales),
            'total_estimado_min' => round($totalMin),
            'total_estimado_max' => round($totalMax),
        ];
    }


    /**
     * @Route("/", name="homepage")
     */
    public function index(Request $request): Response
    {

        // creates a task object and initializes some data for this example
        $log = new Logs(); 
        $log->setDescripcion('');

        $form = $this->createForm(LogsType::class, $log);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $log = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $log->setIdLog(1);
            $log->setFecha(new \DateTime());
    
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $entityManager->persist($log);
    
            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
            // ... perform some action, such as saving the task to the database

            return $this->render('apgijon/index.html.twig', [
                'controller_name' => 'ApgijonController',
                'form' => $form->createView(),
                'cookies'=> 'Mostrar'
            ]);
        }
        
        return $this->render('apgijon/principal.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView(),
            'cookies'=> 'No mostrar'
        ]);
    }

    /**
     * @Route("/nosotros", name="aboutus")
     */
    public function aboutus(Request $request): Response
    {
        return $this->render('apgijon/nosotros.html.twig', [
            'controller_name' => 'ApgijonController',
        ]);
    } 

    /**
     * @Route("/reforma-integral-banos-gijon", name="integral")
     */
    public function integral(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/integral.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }     

    /**
     * @Route("/presupuestoInmediato", name="iapresupuesto")
     */
    public function iapresupuesto(Request $request, ConsultasRepository $consultasRepository, TelegramNotifier $notifier): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);
            $jsonPresupuesto = $form->get('jsonPresupuesto')->getData();
            $datosPresupuesto = json_decode($jsonPresupuesto, true);
            $rootDir = $this->getParameter('kernel.project_dir') . '/public_html';

            $html = $this->renderView('apgijon/pdfpresupuesto.html.twig', [
                'consulta' => $consulta,
                'presupuesto' => $datosPresupuesto, // viene del JSON transformado
                'id' => $consulta->getId(),
                    'root_dir' => $rootDir
            ]);

                $this->pdf->loadHtml($html);
                $this->pdf->render();            

            $filename = 'presupuesto_PRE' . $consulta->getId() . '.pdf';
            $path = $this->getParameter('kernel.project_dir') . '/public_html/pdfs/' . $filename;

            file_put_contents($path, $this->pdf->output()); // guardado físico
                // Envía el PDF por Telegram
            $notifier->sendDocument($path, "📄 Nuevo presupuesto generado:\nNombre: {$consulta->getNombre()}\nTeléfono: {$consulta->getTelefono()}");

            return new Response(
                $this->pdf->output(),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="presupuesto_PRE{{ id }}.pdf"'
                ]
            );            

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/iapresupuesto.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }      

        #[Route('/api/presupuesto/chat', name: 'api_presupuesto_chat', methods: ['POST'])]
        public function chat(Request $request, OpenAIClient $openAIClient, TelegramNotifier $notifier): JsonResponse
        {
            $data = json_decode($request->getContent(), true);
            $messages = $data['messages'] ?? null;

            if (count($messages) === 1 && $messages[0]['role'] === 'system') {
                $notifier->sendMessage("📥 Nuevo usuario ha iniciado un chat de presupuesto.");
            }

            if (!$messages || !is_array($messages)) {
                return $this->json(['reply' => 'No he recibido los mensajes anteriores.']);
            }

            try {
                $respuestaIA = $openAIClient->askWithHistory($messages);
            } catch (\Throwable $e) {
                return $this->json(['reply' => 'Lo siento, ha habido un error al contactar con la IA.']);
            }

            return $this->json(['reply' => $respuestaIA]);
        }




    /**
     * @Route("/cambio-banera-ducha-gijon", name="platoducha")
     */
    public function platoducha(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/platoducha.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()
        ]);
    }   

    /**
     * @Route("/mamaparas-bano-gijon", name="mampara")
     */
    public function mamparas(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/mamparas.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()
        ]);
    }       

    /**
     * @Route("/contacto", name="contacto")
     */
    public function contacto(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('contacto', [], Response::HTTP_SEE_OTHER);
        }        

        return $this->render('apgijon/contacto.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView(),
        ]);
    }   

    /**
     * @Route("/img-route/{img}", name="img_route")
     * A route with one parameter
     */
    public function imagen($img): Response
    {


        //Retrieve the root folder with the kernel and then add the location of the 
        //file
        $filename = $this->getParameter('kernel.project_dir') . '/public_html/img/' . $img;
        //If the file exists then we return it, otherwise return 404
 
        if (file_exists($filename)) {
            //return a new BinaryFileResponse with the file name
           return new BinaryFileResponse($filename);
        } else {
            return new JsonResponse(null, 404);
        }
    }


}
