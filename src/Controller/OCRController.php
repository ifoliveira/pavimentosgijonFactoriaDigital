<?php

namespace App\Controller;

use App\Entity\Forecast;
use App\Entity\Productos;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use thiagoalessio\TesseractOCR\TesseractOCR; 
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\MisClases\Factory\InvoiceDecipherStrategyFactory;
use App\Repository\TiposmovimientoRepository;
use DateTime;
use App\Form\pdfUploadType;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use GuzzleHttp\Client;

/**
 * @Route("/admin/ocr")
 */
class OCRController extends AbstractController
{

    protected $em;
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $em )
    {
        $this->em = $em;
        $this->client = $client;
    }

    /**
     * @Route("/ocrAPI", name="api_ocr")
     */

     public function process(Request $request): Response
     {

        $form = $this->createForm(pdfUploadType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

         // Obtener el archivo del request, asumiendo que el campo se llama 'file'
         $file = $form['pdfFile']->getData();
         if (!$file) {
             return $this->json(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
         }
 
         // Leer el contenido del archivo
         $fileData = file_get_contents($file->getPathname());
 
         $client = new Client();
         try {
            $response = $client->request('POST', 'https://api.ocr.space/parse/image', [
                'headers' => ['apiKey' => 'K84053679088957'],
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($file->getPathname(), 'r'),
                        'filename' => $file->getClientOriginalName(),
                    ],
                    [
                        'name' => 'OCREngine',
                        'contents' => '2'
                    ],
                    [
                        'name' => 'isTable',
                        'contents' => 'true'
                    ],
                    [
                        'name' => 'isOverlayRequired',
                        'contents' => 'true'
                    ],
                ],
            ]);

             $statusCode = $response->getStatusCode();
             $content = $response->getBody()->getContents();
     
  

            $data = json_decode($content, true);

             // Localizar la fecha de vencimiento
             $fechaVencimiento = "";
             foreach ($data['ParsedResults'][0]['TextOverlay']['Lines'] as $line) {
                 if (strpos($line['LineText'], 'VENCIMIENTOS') !== false) {
                     $fechaVencimiento = $line['LineText'];
                     break;
                 }
             }

             echo $fechaVencimiento . "\n";
        // Extraer los productos
        $productos = [];
        $leftPositionThreshold = 125; // Umbral para la posición 'Left' de los productos
        
        
        foreach ($data['ParsedResults'][0]['TextOverlay']['Lines'] as $line) {
            if (isset($line['Words'][0]) && abs($line['Words'][0]['Left'] - $leftPositionThreshold) < 10) {
                // Concatenamos todas las palabras de la línea para formar la descripción del producto
                $altura = $line['MinTop'];
                $descripcion = "";
        
                foreach ($data['ParsedResults'][0]['TextOverlay']['Lines'] as $innerLine) {
                    if (abs($innerLine['MinTop'] - $altura) < 10) { // 10 es el umbral para considerar la misma altura
                        // Agregar la descripción de estas líneas al mismo producto
                        $descripcion .= " " . array_reduce($innerLine['Words'], function ($carry, $word) {
                            return $carry . " " . $word['WordText'];
                        }, '');
                    }
                }
                                
                $productos[] = trim($descripcion) . " " . $altura;
            }
        }        

        echo "Productos:\n";
        foreach ($productos as $producto) {
            echo $producto . "\n";
        }
             


             
           if ($statusCode == 200) {
                 return new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/json']);
             } else {
                 // Si la API devuelve un error, también devolvemos ese contenido como JSON
                 return new Response($content, $statusCode, ['Content-Type' => 'application/json']);
             } 

         } catch (\Exception $e) {
             return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
         }
        }

        return $this->render('ocr/pdf.html.twig', [
            'form' => $form->createView(),
        ]);         
     }



    /**
     * @Route("/pdf", name="app_ocr")
     */
    public function indexpdf(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(pdfUploadType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form['pdfFile']->getData();
            if ($pdfFile) {
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid().'.'.$pdfFile->guessExtension();

                try {
                    $pdfFile->move(
                        '../public_html/uploads/',
                        $newFilename
                    );

                    // Convertir el PDF a JPEG
                    $pdfPath = '../public_html/uploads' . '/' . $newFilename;
                    $outputImagePath = '../public_html/uploads' . '/' . $originalFilename . '.jpg';

                    $process = new 
                    Process([
                        'convert',
                        '-density', '300',
                        '-colorspace', 'gray',
                        '-sharpen', '0x1.0',
                        '-trim', // Esto recorta los márgenes automáticamente
                        '+repage', // Restablece la página a tamaño completo después del recorte
                        $pdfPath,
                        '-background', 'white', // Fondo blanco para evitar bordes transparentes
                        '-alpha', 'remove', // Elimina el canal alfa si es que lo hay
                        '-quality', '100', // Máxima calidad para imágenes resultantes
                        $outputImagePath
                    ]);
                    $process->run();

                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }



                } catch (FileException $e) {
                    // manejar excepción si algo ocurre durante la subida del archivo
                }

                // Procesar el archivo con Tesseract OCR
                $ocr = new TesseractOCR($outputImagePath);
                $text = $ocr->lang('spa')->run(); // Asegúrate de configurar el idioma correcto
                // Identifica al proveedor a partir del texto OCR o alguna otra lógica
                $providerIdentifier = null;

                // Verificar si el texto contiene 'GME'
                if (strpos($text, 'GME') !== false) {
                    $providerIdentifier = 'ProviderGME';
                } elseif (strpos($text, 'FDEZ CAS') !== false) {
                    $providerIdentifier = 'ProviderAFC';
                } elseif (strpos($text, 'KASSANDRA') !== false) {
                    $providerIdentifier = 'ProviderKAS';
                } elseif (strpos($text, 'Cromados Modernos') !== false) {
                    $providerIdentifier = 'ProviderCRM';
                } elseif (strpos($text, 'SALGAR') !== false) {
                    $providerIdentifier = 'ProviderSAL';
                } elseif (strpos($text, 'ROYO SPAIN') !== false) {
                    $providerIdentifier = 'ProviderROY';
                }


                $strategy = InvoiceDecipherStrategyFactory::create($providerIdentifier);
                $invoiceData = $strategy->decipher($text);   
                
                return $this->render('ocr/result.html.twig', [
                    'text' => $invoiceData ?? 'No se pudo procesar el texto.',
                    'completo' => $text
                ]);                

            }
        }

        return $this->render('ocr/pdf.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    

    /**
     * @Route("/insertar", name="producto_insertar", methods={"POST"})
     */

    public function insertar(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $producto = new Productos();
        $producto->setStockPd($data['cantidad']);
        $producto->setDescripcionPd($data['descripcion']);
        $precio = $data['precio']*1.262;
        $precio = round($precio,2);
        $producto->setPrecioPd($precio);
        $pvp = $data['precio']* 1.262 * (1 + ($data['descuento'] / 100));
        $pvp = round($pvp,2);
        $ajustes = [-0.01, -0.05, -0.10, -0.20];
        $ajusteSeleccionado = $ajustes[array_rand($ajustes)];
        $pvp =ceil($pvp) + 1  + $ajusteSeleccionado;
        $producto->setPvpPd($pvp);

        $this->em->persist($producto);
        $this->em->flush();

        return $this->json(['status' => 'Producto insertado con éxito']);
    }    


    /**
     * @Route("/forecast", name="forecast_insertar", methods={"POST"})
     */

     public function forecast(Request $request, TiposmovimientoRepository $tiposmovimientoRepository): Response
     {
         $data = json_decode($request->getContent(), true);
         $tipomovimiento = $tiposmovimientoRepository->findOneBy(array('descripcionTm' => 'Proveedor'));
         $forecast = new Forecast();
         $forecast->setTipoFr($tipomovimiento);
         $forecast->setConceptoFr('Factura ' . $data['proveedor'] );
         $forecast->setOrigenFr('Banco');
         $forecast->setFijovarFr('V');
         $forecast->setEstadoFr('P');
         $formato = 'd/m/Y'; // El formato en que se proporciona la fecha

         // Crear el objeto DateTime con el formato específico
         $fecha = DateTime::createFromFormat($formato, $data['fechaVencimiento']);    
        
         $forecast->setFechaFr($fecha);

         $forecast->setImporteFr($data['importeTotal']);
          $this->em->persist($forecast);
         $this->em->flush();
 
         return $this->json(['status' => 'Forecast insertado con éxito']);
     }       
}
