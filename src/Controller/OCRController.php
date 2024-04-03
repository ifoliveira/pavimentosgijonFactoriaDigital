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

/**
 * @Route("/admin/ocr")
 */
class OCRController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
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
