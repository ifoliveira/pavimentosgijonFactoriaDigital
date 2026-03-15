<?php

namespace App\Controller;

use App\Entity\Consultas;
use App\Entity\Visitante;
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
use App\Service\OpenAIClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\TelegramNotifierService;
use Dompdf\Dompdf; 
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\TrackingService;

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

        #[Route('/api/presupuesto/calculate2', name: 'api_presupuesto_calculate2', methods: ['POST'])]
        public function calcular(Request $request, TelegramNotifierService $notifier): JsonResponse
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
            $detalles = [
                'mano_obra' => [],
                'materiales' => []
            ];

            $largo = $datos['medidas_bano']['largo_m'];
            $ancho = $datos['medidas_bano']['ancho_m'];
            $alto = $datos['medidas_bano']['alto_m'];

            $m2_paredes = 2 * ($largo + $ancho) * $alto;
            $m2_suelo = $largo * $ancho;

            // 🧱 MANO DE OBRA
            $manoObra += $p['mano_obra']['albañil']['reforma_completa'];
            $detalles['mano_obra'][] = ['concepto' => 'Albañil - Reforma completa', 'importe' => $p['mano_obra']['albañil']['reforma_completa']];

            $puntosAgua = 1; // inodoro
            $detalles['mano_obra'][] = ['concepto' => 'Fontanero - Punto agua: Inodoro', 'importe' => $p['mano_obra']['fontanero_base']];

            if (!empty($datos['mueble_lavabo']['ancho_cm'])) {
                $puntosAgua++;
                $detalles['mano_obra'][] = ['concepto' => 'Fontanero - Punto agua: Lavabo', 'importe' => $p['mano_obra']['fontanero_base']];
            }

            if (!empty($datos['sanitarios']['ducha_o_banera']['tipo'])) {
                $puntosAgua++;
                $detalles['mano_obra'][] = ['concepto' => 'Fontanero - Punto agua: Ducha/Bañera', 'importe' => $p['mano_obra']['fontanero_base']];
            }

            if (!empty($datos['griferia']['instalar_barra_ducha'])) {
                $puntosAgua++;
                $detalles['mano_obra'][] = ['concepto' => 'Fontanero - Punto agua: Barra de ducha', 'importe' => $p['mano_obra']['fontanero_base']];
            }

            if (!empty($datos['sanitarios']['bide']['hay_bide_actual']) && empty($datos['sanitarios']['bide']['suprimir'])) {
                $puntosAgua++;
                $detalles['mano_obra'][] = ['concepto' => 'Fontanero - Punto agua: Bidé', 'importe' => $p['mano_obra']['fontanero_base']];
            }

            if (!empty($datos['fontaneria']['llaves_corte'])) {
                $puntosAgua++;
                $detalles['mano_obra'][] = ['concepto' => 'Fontanero - Llaves de corte', 'importe' => $p['mano_obra']['fontanero_base']];
            }

            $manoObra += ($puntosAgua - 1) * $p['mano_obra']['fontanero_base']; // ya se sumó el primero arriba

            $manoObra += $p['mano_obra']['pintura_techo'];
            $detalles['mano_obra'][] = ['concepto' => 'Pintura techo', 'importe' => $p['mano_obra']['pintura_techo']];

            $manoObra += $p['mano_obra']['electricista_base'];
            $detalles['mano_obra'][] = ['concepto' => 'Electricista base', 'importe' => $p['mano_obra']['electricista_base']];

            $manoObra += $p['mano_obra']['jefe_obra'];
            $detalles['mano_obra'][] = ['concepto' => 'Jefe de obra', 'importe' => $p['mano_obra']['jefe_obra']];

            $manoObra += $p['mano_obra']['mampara_instalacion'];
            $detalles['mano_obra'][] = ['concepto' => 'Instalación mampara', 'importe' => $p['mano_obra']['mampara_instalacion']];

            if (!empty($datos['griferia']['instalar_barra_ducha'])) {
                $manoObra += $p['mano_obra']['barra_ducha_extra'];
                $detalles['mano_obra'][] = ['concepto' => 'Extra barra de ducha', 'importe' => $p['mano_obra']['barra_ducha_extra']];
            }

            if (!empty($datos['techo']['reinstalar_escayola'])) {
                $manoObra += $p['mano_obra']['escayola_instalacion'];
                $detalles['mano_obra'][] = ['concepto' => 'Instalación escayola', 'importe' => $p['mano_obra']['escayola_instalacion']];
            }

            if (!empty($datos['instalar_radiador_toallero'])) {
                $manoObra += $p['mano_obra']['radiador_toallero_instalacion'];
                $detalles['mano_obra'][] = ['concepto' => 'Instalación radiador toallero', 'importe' => $p['mano_obra']['radiador_toallero_instalacion']];
            }

            if ($manoObra < $p['mano_obra']['minimo']) {
                $detalles['mano_obra'][] = ['concepto' => 'Mínimo de mano de obra aplicado', 'importe' => $p['mano_obra']['minimo'] - $manoObra];
                $manoObra = $p['mano_obra']['minimo'];
            }

            // 🧱 MATERIALES
            $materiales += $m2_paredes * $p['materiales']['azulejos']['precio_m2'];
            $detalles['materiales'][] = ['concepto' => 'Azulejos pared', 'importe' => round($m2_paredes * $p['materiales']['azulejos']['precio_m2'])];

            $materiales += $m2_suelo * $p['materiales']['pavimento']['precio_m2'];
            $detalles['materiales'][] = ['concepto' => 'Pavimento suelo', 'importe' => round($m2_suelo * $p['materiales']['pavimento']['precio_m2'])];

            $materiales += $p['materiales']['cola']['bano_completo'];
            $detalles['materiales'][] = ['concepto' => 'Cola para alicatado', 'importe' => $p['materiales']['cola']['bano_completo']];

            $tipoSanitario = $datos['sanitarios']['ducha_o_banera']['tipo'] ?? null;
            if ($tipoSanitario && isset($p['materiales']['sanitarios'][$tipoSanitario])) {
                $materiales += $p['materiales']['sanitarios'][$tipoSanitario];
                $detalles['materiales'][] = ['concepto' => ucfirst($tipoSanitario), 'importe' => $p['materiales']['sanitarios'][$tipoSanitario]];
            }
            // Mampara
            $tipoMampara = $datos['mampara']['tipo'];
            $importe = $p['materiales']['mampara'][$tipoMampara] ?? 0;
            $materiales += $importe;
            if ($importe > 0) {
                $detalles['materiales'][] = ['concepto' => "Mampara ($tipoMampara)", 'importe' => $importe];
            }


            $grifo = !empty($datos['griferia']['instalar_barra_ducha'])
                ? $p['materiales']['griferia']['barra_ducha']
                : $p['materiales']['griferia']['estandar'];

            $materiales += $grifo;
            $detalles['materiales'][] = ['concepto' => 'Grifería', 'importe' => $grifo];

            $ancho = $datos['mueble_lavabo']['ancho_cm'] ?? null;
            $precioLavabo = match (true) {
                $ancho <= 60 => $p['materiales']['muebles']['lavabo'][60],
                $ancho <= 80 => $p['materiales']['muebles']['lavabo'][80],
                $ancho >= 100 => $p['materiales']['muebles']['lavabo'][100],
                default => $p['materiales']['muebles']['lavabo'][80],
            };

            $materiales += $precioLavabo;
            $detalles['materiales'][] = ['concepto' => 'Mueble lavabo', 'importe' => $precioLavabo];

            $materiales += $p['materiales']['muebles']['espejo'];
            $detalles['materiales'][] = ['concepto' => 'Espejo', 'importe' => $p['materiales']['muebles']['espejo']];

            $materiales += $p['materiales']['sanitarios']['inodoro'];
            $detalles['materiales'][] = ['concepto' => 'Inodoro', 'importe' => $p['materiales']['sanitarios']['inodoro']];

            $materiales += $p['materiales']['electricidad']['foco'];
            $detalles['materiales'][] = ['concepto' => 'Foco LED', 'importe' => $p['materiales']['electricidad']['foco']];

            if (!empty($datos['instalar_radiador_toallero'])) {
                $materiales += $p['materiales']['radiador_toallero'];
                $detalles['materiales'][] = ['concepto' => 'Radiador toallero', 'importe' => $p['materiales']['radiador_toallero']];
            }

            if (!empty($datos['techo']['reinstalar_escayola'])) {
                $materiales += $p['materiales']['escayola'];
                $detalles['materiales'][] = ['concepto' => 'Escayola', 'importe' => $p['materiales']['escayola']];
            }

            if (!empty($datos['sanitarios']['bide']['hay_bide_actual']) && empty($datos['sanitarios']['bide']['suprimir'])) {
                $materiales += $p['materiales']['sanitarios']['bide'];
                $materiales += $p['materiales']['griferia']['bide'];
                $detalles['materiales'][] = ['concepto' => 'Bidé', 'importe' => $p['materiales']['sanitarios']['bide']];
                $detalles['materiales'][] = ['concepto' => 'Grifería bidé', 'importe' => $p['materiales']['griferia']['bide']];
            }

            $totalMin = $manoObra + $materiales;
            $totalMax = $totalMin + 100;

            return [
                'mano_obra' => round($manoObra),
                'materiales' => round($materiales),
                'total_estimado_min' => round($totalMin),
                'total_estimado_max' => round($totalMax),
                'detalles' => $detalles,
            ];
        }


private function calcularPresupuestoDucha(array $datos): array
{
    $p = $this->precios;
    $manoObra = 0;
    $materiales = 0;
    $detalles = [
        'mano_obra' => [],
        'materiales' => []
    ];

    $altura = $datos['zona_azulejos']['altura_reforma'];
    $entreParedes = $datos['entre_paredes'];
    $longitud = $datos['medidas_bañera']['largo_cm'] / 100;

    // 🧱 Mano de obra
    $importe = match ($altura) {
        '1m' => $p['mano_obra']['albañil']['hasta_1m'],
        'techo' => $p['mano_obra']['albañil']['hasta_techo'],
        default => 0
    };
    $manoObra += $importe;
    $detalles['mano_obra'][] = ['concepto' => "Albañil - Alicatado hasta $altura", 'importe' => $importe];

    $manoObra += $p['mano_obra']['fontanero_base'];
    $detalles['mano_obra'][] = ['concepto' => 'Fontanero - instalación básica', 'importe' => $p['mano_obra']['fontanero_base']];

    if (!empty($datos['griferia']['instalar_barra_ducha'])) {
        $manoObra += $p['mano_obra']['barra_ducha_extra'];
        $detalles['mano_obra'][] = ['concepto' => 'Fontanero - instalación barra de ducha', 'importe' => $p['mano_obra']['barra_ducha_extra']];
    }

    if ($altura === 'techo') {
        $manoObra += $p['mano_obra']['pintura_techo'];
        $detalles['mano_obra'][] = ['concepto' => 'Pintura techo', 'importe' => $p['mano_obra']['pintura_techo']];
    }

    if ($datos['mampara']['tipo'] !== 'ninguna') {
        $manoObra += $p['mano_obra']['mampara_instalacion'];
        $detalles['mano_obra'][] = ['concepto' => 'Instalación mampara', 'importe' => $p['mano_obra']['mampara_instalacion']];
    }

    $manoObra += $p['mano_obra']['jefe_obra'];
    $detalles['mano_obra'][] = ['concepto' => 'Coordinación jefe de obra', 'importe' => $p['mano_obra']['jefe_obra']];

    if ($manoObra < $p['mano_obra']['minimo']) {
        $detalles['mano_obra'][] = ['concepto' => 'Aplicado mínimo de mano de obra', 'importe' => $p['mano_obra']['minimo'] - $manoObra];
        $manoObra = $p['mano_obra']['minimo'];
    }

    // 🧱 Materiales

    // Plato de ducha
    if ($longitud <= 1.2) {
        $importe = $p['materiales']['plato_ducha']['hasta_120'];
        $materiales += $importe;
        $detalles['materiales'][] = ['concepto' => 'Plato de ducha hasta 120 cm', 'importe' => $importe];
    } elseif ($longitud <= 1.6) {
        $importe = $p['materiales']['plato_ducha']['hasta_160'];
        $materiales += $importe;
        $detalles['materiales'][] = ['concepto' => 'Plato de ducha hasta 160 cm', 'importe' => $importe];
    } else {
        $importe = $p['materiales']['plato_ducha']['mayor'];
        $materiales += $importe;
        $detalles['materiales'][] = ['concepto' => 'Plato de ducha mayor de 160 cm', 'importe' => $importe];
    }

    // Mampara
    $tipoMampara = $datos['mampara']['tipo'];
    $importe = $p['materiales']['mampara'][$tipoMampara] ?? 0;
    $materiales += $importe;
    if ($importe > 0) {
        $detalles['materiales'][] = ['concepto' => "Mampara ($tipoMampara)", 'importe' => $importe];
    }

    // Grifería
    $grifo = !empty($datos['griferia']['instalar_barra_ducha'])
        ? $p['materiales']['griferia']['barra_ducha']
        : $p['materiales']['griferia']['estandar'];
    $materiales += $grifo;
    $detalles['materiales'][] = ['concepto' => 'Grifería', 'importe' => $grifo];

    // Azulejos
    if (!empty($datos['zona_azulejos']['derribo'])) {
        $paredes = $entreParedes ? 2 : 1;
        $altura_m = $altura === 'techo' ? 2.4 : ($altura === '1m' ? 1 : 0.5);
        $m2 = $paredes * $longitud * $altura_m;

        $importeAzulejos = $m2 * $p['materiales']['azulejos']['precio_m2'];
        $materiales += $importeAzulejos;
        $detalles['materiales'][] = ['concepto' => 'Azulejos (m²)', 'importe' => round($importeAzulejos)];

        $materiales += $p['materiales']['cola']['base'];
        $detalles['materiales'][] = ['concepto' => 'Cola base para alicatado', 'importe' => $p['materiales']['cola']['base']];

        if ($altura === 'techo') {
            $materiales += $p['materiales']['cola']['extra_techo'];
            $detalles['materiales'][] = ['concepto' => 'Cola extra por altura hasta techo', 'importe' => $p['materiales']['cola']['extra_techo']];
        }
    }

    if (($datos['zona_azulejos']['reponer_azulejos'] ?? '') === 'buscar_similar') {
        $materiales += $p['materiales']['azulejos_similares_extra'];
        $detalles['materiales'][] = ['concepto' => 'Suplemento por azulejos similares', 'importe' => $p['materiales']['azulejos_similares_extra']];
    }

    $totalMin = $manoObra + $materiales;
    $totalMax = $totalMin + 100;

    return [
        'mano_obra' => round($manoObra),
        'materiales' => round($materiales),
        'total_estimado_min' => round($totalMin),
        'total_estimado_max' => round($totalMax),
        'detalles' => $detalles,
    ];
}


    #[Route('/homepage', name: 'homepage2')]
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


    #[Route('/', name: 'homepage')]
    public function index2(Request $request, EntityManagerInterface $em): Response
    {

       return $this->render('home/index_decision.html.twig', [
           'controller_name' => 'ApgijonController',
       ]);
    }

    #[Route('/nosotros', name: 'aboutus')]
    public function aboutus(Request $request): Response
    {
        return $this->render('apgijon/nosotros.html.twig', [
            'controller_name' => 'ApgijonController',
        ]);
    } 

    #[Route('/reforma-integral-banos-gijon', name: 'integral')]
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

        return $this->render('apgijon/integral.html2.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }     


    #[Route('/reforma-bano-en-gijon', name: 'reformabanogijon')]
    public function reformabanogijon(Request $request, ConsultasRepository $consultasRepository): Response
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

        return $this->render('apgijon/reformabanogijon.html2.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }     
        
    #[Route('/presupuestoInmediato', name: 'iapresupuesto')]
    public function iapresupuestoIA(Request $request, ConsultasRepository $consultasRepository, TelegramNotifierService $notifier, TrackingService $tracking): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);
            $jsonPresupuesto = $form->get('jsonPresupuesto')->getData();
            $datosPresupuesto = json_decode($jsonPresupuesto, true);

            if (is_array($datosPresupuesto)) {
                $consulta->setPresupuestoAI($datosPresupuesto); // 👈 aquí guardamos todo
            }            

            $consultasRepository->add($consulta, true);
            $jsonPresupuesto = $form->get('jsonPresupuesto')->getData();
            $datosPresupuesto = json_decode($jsonPresupuesto, true);
            $filename = 'presupuesto_' . $consulta->getId() . '.json';
            $pathJson = $this->getParameter('kernel.project_dir') . '/public_html/pdfs/' . $filename;

            file_put_contents($pathJson, json_encode($datosPresupuesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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
            $notifier->sendDocument($pathJson, "🧾 JSON completo (modo desarrollador)");

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

        $tracking->track('click_presupuesto', [
              'referer' => $request->headers->get('referer')
        ]);

        return $this->render('ia/iapresupuesto_2.html.twig', [
             'form' => $form->createView()
                ]);
           }


        #[Route('/api/presupuesto/chat-track', name: 'api_presupuesto_track', methods: ['POST'])]
        public function trackChat(Request $request, TelegramNotifierService $notifier): JsonResponse
        {
            $data = json_decode($request->getContent() ?: '[]', true) ?: [];

            $evento   = $data['evento']   ?? 'user_interaction';
            $tipo     = $data['tipo']     ?? 'desconocido';      // "ducha" | "completo"
            $chatId   = $data['chatId']   ?? substr(sha1($request->getClientIp().microtime()), 0, 10);
            $pregunta = $data['pregunta'] ?? null;
            $respuesta= $data['respuesta']?? null;

            // Sanitizar / limitar longitud
            $esc = static function (?string $t): string {
                if ($t === null) return '—';
                $t = mb_substr($t, 0, 500);
                // Escapar Markdown de Telegram (modo Markdown estándar)
                $repl = ['*'=>'\*','_'=>'\_','['=>'\[',']'=>'\]','('=>'\(',')'=>'\)','~'=>'\~','`'=>'\`','>'=>'\>','#'=>'\#','+'=>'\+','-'=>'\-','='=>'\=','|'=>'\|','{'=>'\{','}'=>'\}','.'=>'\.','!'=>'\!'];
                return strtr($t, $repl);
            };

            $tipoLabel = match ($tipo) {
                'ducha'    => '🚿 Cambio bañera → plato',
                'completo' => '🏗️ Reforma completa',
                default    => '❔ No especificado'
            };

            // Plantillas por evento
            $titleByEvent = [
                'tipo_seleccionado'    => '🔔 *Tipo de reforma seleccionado*',
                'first_question_shown' => '🧩 *Primera pregunta mostrada*',
                'user_response'        => '✍️ *Respuesta del usuario*',
                'descargar_pdf_click'  => '📄 *Click en Descargar PDF*',
                'first_question_abandon'=> '⏳ *Posible abandono en 1ª pregunta*',
                'user_interaction'     => '💬 *Interacción en el chat*',
            ];

        
            $titulo = $titleByEvent[$evento] ?? $titleByEvent['user_interaction'];

            // Construcción del mensaje según el evento
            switch ($evento) {

                case 'first_question_shown':
                    // Primera pregunta → tipo + IA
                    $mensaje = implode("\n", [
                        "🧩 *Primera pregunta mostrada*",
                        "• Tipo: *{$esc($tipoLabel)}*",
                        "❓ *IA:* {$esc($pregunta)}",
                    ]);
                    break;

                case 'user_response':
                    // Interacción del usuario → solo su respuesta
                    $mensaje = implode("\n", [
                        "✍️ *Respuesta del usuario*",
                        "💬 {$esc($respuesta)}",
                    ]);
                    break;

                case 'tipo_seleccionado':
                    // Tipo seleccionado → solo el tipo
                    $mensaje = implode("\n", [
                        "🔔 *Tipo de reforma seleccionado*",
                        "• Tipo: *{$esc($tipoLabel)}*",
                    ]);
                    break;

                default:
                    // Otros eventos → plantilla genérica
                    $mensaje = implode("\n", [
                        "💬 *Interacción* {$esc($evento)}",
                        "❓ *IA:* {$esc($pregunta)}",
                        "💬 *Usuario:* {$esc($respuesta)}",
                    ]);
                    break;
            }


            try {
                $notifier->sendMessage($mensaje); // tu servicio ya lo envía por Telegram en Markdown
                return $this->json(['ok' => true]);
            } catch (\Throwable $e) {
                return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
        }


        #[Route('/api/presupuesto/chat', name: 'api_presupuesto_chat', methods: ['POST'])]
        public function chat(Request $request, OpenAIClient $openAIClient, TelegramNotifierService $notifier): JsonResponse
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


    #[Route('/cambio-banera-ducha-gijon', name: 'platoducha')]
    public function cambioBaneraDuchaV2(): Response
    {
        return $this->render('apgijon/cambio_banera_ducha_v2.html.twig');
    }


    #[Route('/cambio-banera-ducha-gijon-v2', name: 'platoducha_v2')]
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

    #[Route('/mamaparas-bano-gijon', name: 'mamparas_antigua')]
    public function mamparasAntigua(): RedirectResponse
    {
        return new RedirectResponse(
            '/mamparas-bano-gijon',
            301
        );
    }


    #[Route('/mamparas-bano-gijon', name: 'mampara')]
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

    #[Route('/contacto', name: 'contacto')]
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

    #[Route('/img-route/{img}', name: 'img_route')]
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
