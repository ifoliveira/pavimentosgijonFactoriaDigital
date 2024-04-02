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
     * @Route("/", name="app_ocr")
     */
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Crear un formulario simple para subir archivos
        $form = $this->createFormBuilder()
            ->add('file', FileType::class)
            ->getForm();
    
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData(); // Obtener el archivo subido
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid().'.'.$file->guessExtension();
    
                // Mover el archivo al directorio donde lo procesaremos
                try {
                    $file->move(
                        $this->getParameter('kernel.project_dir').'/public_html/uploads',
                        $newFilename
                    );
    
                    // Procesar el archivo con Tesseract OCR
                    $ocr = new TesseractOCR('../public_html/uploads/'.$newFilename);
                    $text = $ocr->lang('eng')->run(); // Asegúrate de configurar el idioma correcto
                    // Identifica al proveedor a partir del texto OCR o alguna otra lógica
                    $providerIdentifier = null;

                    // Verificar si el texto contiene 'GME'
                    if (strpos($text, 'GME') !== false) {
                        $providerIdentifier = 'ProviderGME';
                    } elseif (strpos($text, 'CASTELLANOS') !== false) {
                        $providerIdentifier = 'ProviderAFC';
                    } elseif (strpos($text, 'KASSANDRA') !== false) {
                        $providerIdentifier = 'ProviderGME';
                    } elseif (strpos($text, 'Cromados Modernos') !== false) {
                        $providerIdentifier = 'ProviderCRM';
                    }


                    $strategy = InvoiceDecipherStrategyFactory::create($providerIdentifier);
                    $invoiceData = $strategy->decipher($text);                 
    
                    // Aquí podrías hacer algo con el texto extraído, como guardarlo en la base de datos
                    // Pero por ahora, solo lo enviaremos de vuelta a la vista
                } catch (FileException $e) {
                    // Manejar excepción si algo sale mal durante la carga del archivo
                }
            }
    
            return $this->render('ocr/result.html.twig', [
                'text' => $invoiceData ?? 'No se pudo procesar el texto.',
                'completo' => $text
            ]);
        }
    
        return $this->render('ocr/index.html.twig', [
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

                    $process = new Process(['convert', '-density', '300', $pdfPath, $outputImagePath]);
                    $process->run();

                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }



                } catch (FileException $e) {
                    // manejar excepción si algo ocurre durante la subida del archivo
                }

                // Redirigir o manejar la lógica post subida
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

        $producto->setPrecioPd($data['precio']);
        $producto->setPvpPd($data['precio'] * 1.40);

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
