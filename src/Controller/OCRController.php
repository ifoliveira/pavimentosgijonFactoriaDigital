<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use thiagoalessio\TesseractOCR\TesseractOCR; 
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\MisClases\Factory\InvoiceDecipherStrategyFactory;

/**
 * @Route("/admin/ocr")
 */
class OCRController extends AbstractController
{
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
                    $providerIdentifier = "ProviderAFC";   
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
}
