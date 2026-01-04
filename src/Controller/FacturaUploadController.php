<?php

namespace App\Controller;

use App\MisClases\FacturaPdfToJsonService;
use App\Service\FacturaProcessorService;
use App\Service\ForecastHandlerService;
use App\Service\ProductoHandlerService;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Entity\Forecast;
use App\Entity\Tiposmovimiento;
use App\Form\ForecastType;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Productos;
use App\Entity\Tipoproducto;
use App\Form\ProductosType;



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
    public function subir(Request $request, 
                          FacturaProcessorService $processor,
                          ForecastHandlerService $forecastHandler,
                          ProductoHandlerService $productoHandler,
    ): Response
    {
        $datos = null;
        $pdfFilename = null;
        $session = $request->getSession();
        $formLoop = [];
        $productosForms = [];
        $usarImagen = $request->request->get('usar_imagen') === '1';


        // 1. Subida y análisis de la factura
        if ($request->isMethod('POST') && $request->files->has('factura')) {
            try {
                [$pdfFilename, $datos] = $processor->procesarFacturaDesdeRequest($request);
                $session->set('factura_datos', $datos);
                $session->set('factura_pdf', $pdfFilename);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Error al procesar la factura: ' . $e->getMessage());
            }
        } else {
            $datos = $session->get('factura_datos');
            $pdfFilename = $session->get('factura_pdf');
        }

      
        // 2. Formularios de vencimientos
        if (is_array($datos)) {
            $formLoop = $forecastHandler->crearFormulariosForecast($datos, $request);

            if (isset($formLoop['redirect']) && $formLoop['redirect']) {
                $this->addFlash('success', 'Movimiento guardado correctamente');
                return $this->redirectToRoute('factura_subir');
            }
        }



        // 3. Formularios de productos
        $productosForm = $productoHandler->crearFormularioProductos($datos, $request);

        if ($productosForm->isSubmitted() && $productosForm->isValid()) {
            $productoHandler->guardarProductosDesdeFormulario($productosForm);
            $this->addFlash('success', 'Productos procesados');
            return $this->redirectToRoute('factura_subir');
        }        

        

        return $this->render('factura/facturas_tratar.html.twig', [
            'datos' => $datos,
            'pdfFilename' => $pdfFilename,
            'form_loop' => array_map(fn($f) => $f->createView(), $formLoop),
            'articulos' => $datos['articulos'] ?? [],
            'productos_form' => $productosForm->createView(),
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