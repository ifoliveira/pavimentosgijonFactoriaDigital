<?php

namespace App\Controller;

use App\MisClases\FacturaPdfToJsonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\FormFactoryInterface;
use App\Entity\Forecast;
use App\Entity\Tiposmovimiento;
use App\Form\ForecastType;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Productos;
use App\Entity\Tipoproducto;



class FacturaUploadController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }

    #[Route('/factura/nueva', name: 'factura_nueva')]
    public function nuevaFactura(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('factura_datos');
        $session->remove('factura_pdf');
    
        $this->addFlash('info', 'Se ha iniciado una nueva factura');
        return $this->redirectToRoute('factura_subir');
    }

    #[Route('/factura/subir', name: 'factura_subir')]
    public function subir(Request $request, FacturaPdfToJsonService $servicio, SluggerInterface $slugger, FormFactoryInterface $formFactory): Response
    {
        $datos = null;
        $pdfFilename = null;
        $session = $request->getSession();
        $formLoop = [];
     
      
        if ($request->isMethod('POST') && $request->files->has('factura')) {
            /** @var UploadedFile $pdfFile */
            $pdfFile = $request->files->get('factura');

            if ($pdfFile && $pdfFile->isValid()) {
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $pdfFilename = $safeFilename . '-' . uniqid() . '.' . $pdfFile->guessExtension();

                try {
                    $pdfFile->move(
                        $this->getParameter('kernel.project_dir') . '/public_html/var/facturas/',
                        $pdfFilename
                    );

                    // Procesar el PDF con OpenAI
                    $ruta = $this->getParameter('kernel.project_dir') . '/public_html/var/facturas/' . $pdfFilename;
                    $datos = $servicio->procesarFacturaPdf($ruta);
                    //$json = file_get_contents($this->getParameter('kernel.project_dir') . '/public_html/var/facturas/factura_mock.json');
                    //$datos = json_decode($json, true);

                    $session->set('factura_datos', $datos);
                    $session->set('factura_pdf', $pdfFilename);

                } catch (FileException $e) {
                    $this->addFlash('error', 'Error al subir el archivo: ' . $e->getMessage());
                }
            }
        } else {
            // Recuperar los datos si no hay nuevo PDF
            $datos = $session->get('factura_datos');
            $pdfFilename = $session->get('factura_pdf');
        }
        
        $formulariosEnviados = array_keys($request->request->all());
        $indiceGuardado = null;

        foreach ($formulariosEnviados as $clave) {
            if (str_starts_with($clave, 'forecast_')) {
                $indiceGuardado = (int) str_replace('forecast_', '', $clave);
                break;
            }
        }

        if (is_array($datos) && isset($datos['vencimientos']) && is_array($datos['vencimientos'])) {
            foreach ($datos['vencimientos'] as $i => $vencimiento) {
                $forecast = new Forecast();
                $rawFecha = str_replace('-', '/', $vencimiento['fecha']);
                $fecha = \DateTime::createFromFormat('d/m/Y', $rawFecha);            

                $forecast->setFechaFr($fecha);
                $forecast->setImporteFr((float) str_replace(',', '.', $vencimiento['importe']) * -1);
                $forecast->setConceptoFr('Factura ' . ($datos['empresa_emisora']['nombre'] ?? ''));
                $forecast->setOrigenFr('Banco');
                $forecast->setFijovarFr('V');
                $forecast->setEstadoFr('P');
                $forecast->setTipoFr($this->em->getRepository(Tiposmovimiento::class)->findOneBy(['descripcionTm' => 'Proveedor']));
            
                $form = $formFactory->createNamed("forecast_$i", ForecastType::class, $forecast);
                $form->handleRequest($request);
            
                if ($i === $indiceGuardado && $form->isSubmitted() && $form->isValid()) {
                    $this->em->persist($forecast);
                    $this->em->flush();
            
                    // Eliminar el vencimiento procesado de sesión
                    $vencimientos = $datos['vencimientos'];
                    unset($vencimientos[$i]);
                    $datos['vencimientos'] = array_values($vencimientos);
                    $session->set('factura_datos', $datos);
            
                    $this->addFlash('success', 'Movimiento guardado correctamente');
                    return $this->redirectToRoute('factura_subir');
                }
            
                $formLoop[] = $form;
            }
        } else {
            $this->addFlash('warning', 'No hay vencimientos disponibles para procesar.');
        }
                

        return $this->render('factura/subir.html.twig', [
            'datos' => $datos,
            'pdfFilename' => $pdfFilename,
            'form_loop' => array_map(fn($f) => $f->createView(), $formLoop),
            'articulos' => $datos['articulos'] ?? []
               ]);
    }


    
    #[Route('/factura/guardar-articulos', name: 'guardar_articulos', methods: ['POST'])]
    public function guardarArticulos(Request $request, EntityManagerInterface $em): Response
    {
        $articulosRaw = $request->request->all('articulos');
    
        $redondearDiezCentimos = function(float $valor): float {
            return round($valor * 10) / 10;
        };

        $agrupados = [];

        foreach ($articulosRaw as $json) {
            $datos = json_decode($json, true);
            if (!$datos || !isset($datos['descripcion'])) {
                continue;
            }
        
            $clave = $datos['descripcion'];
        
            if (!isset($agrupados[$clave])) {
                $agrupados[$clave] = $datos;
            } else {
                $agrupados[$clave]['cantidad'] += (int) $datos['cantidad'];
            }
        }

        
        foreach ($agrupados as $datos) {

            $descripcion = $datos['descripcion'];
            $cantidad = (int) $datos['cantidad'];
        
            // Buscar si ya existe un producto con la misma descripción
            $producto = $em->getRepository(Productos::class)->findOneBy([
                'descripcion_Pd' => $descripcion
            ]);
        
            if ($producto) {
                // Si existe, sumar stock
                $producto->setStockPd($producto->getStockPd() + $cantidad);

            } else {
                // Si no existe, crear uno nuevo
                $producto = new Productos();
                $producto->setDescripcionPd($descripcion);
                $producto->setPrecioPd($redondearDiezCentimos((float) $datos['importe_final'] * 1.262));
                $producto->setPvpPd($redondearDiezCentimos((float) $datos['importe_final'] * 1.40 * 1.262));
                $producto->setStockPd($cantidad);
                $producto->setFecAltaPd(new \DateTime());
                $producto->setObsoleto(false);
        
                $tipo = $em->getRepository(Tipoproducto::class)->findOneBy([]);
                if ($tipo) {
                    $producto->setTipoPdId($tipo);
                }
        
                $em->persist($producto);
            }
            
        }
    
        $em->flush();
    
        return $this->redirectToRoute('factura_subir');
    }

  
    
}