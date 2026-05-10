<?php

namespace App\Controller;

use App\Entity\FacturaProveedor;
use App\Entity\FacturaProveedorLineaAsignacion;
use App\Entity\Forecast;
use App\Entity\Productos;
use App\Entity\ProyectoGasto;
use App\Repository\FacturaProveedorRepository;
use App\Repository\ProyectoRepository;
use App\Repository\TipoproductoRepository;
use App\Repository\TiposmovimientoRepository;
use App\Service\FacturaPdfToJsonService;
use App\Service\FacturaProveedor\FacturaProveedorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/facturas-proveedor')]
class FacturaProveedorController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'factura_proveedor_index', methods: ['GET'])]
    public function index(FacturaProveedorRepository $repo): Response
    {
        return $this->render('factura_proveedor/index.html.twig', [
            'facturas' => $repo->findUltimas(),
        ]);
    }

    #[Route('/pendientes', name: 'factura_proveedor_pendientes', methods: ['GET'])]
    public function pendientes(FacturaProveedorRepository $repo): Response
    {
        return $this->render('factura_proveedor/index.html.twig', [
            'facturas' => $repo->findPendientesAsignacion(),
            'titulo' => 'Facturas pendientes de asignar',
        ]);
    }

    #[Route('/subir', name: 'factura_proveedor_subir', methods: ['GET', 'POST'])]
    public function subir(
        Request $request,
        FacturaPdfToJsonService $facturaPdfToJsonService,
        FacturaProveedorService $facturaProveedorService
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->render('factura_proveedor/subir.html.twig');
        }

        $archivo = $request->files->get('factura');

        if (!$archivo) {
            return $this->json(['error' => 'No se ha recibido ningún archivo.'], 400);
        }

        if (strtolower($archivo->getClientOriginalExtension()) !== 'pdf') {
            return $this->json(['error' => 'Solo se admiten archivos PDF.'], 400);
        }

        try {
            $directorioFacturas = $this->getParameter('kernel.project_dir') . '/var/facturas-proveedor';

            if (!is_dir($directorioFacturas)) {
                mkdir($directorioFacturas, 0775, true);
            }

            $nombreArchivo = uniqid('factura_proveedor_', true) . '.pdf';
            $archivoOriginal = $archivo->getClientOriginalName();

            $archivo->move($directorioFacturas, $nombreArchivo);

            $rutaPdfAbsoluta = $directorioFacturas . '/' . $nombreArchivo;
            $rutaPdfRelativa = 'var/facturas-proveedor/' . $nombreArchivo;

            $resultado = $facturaPdfToJsonService->procesarFacturaPdf($rutaPdfAbsoluta);

            $facturaProveedor = $facturaProveedorService->crearDesdeJson(
                $resultado,
                $rutaPdfRelativa,
                $archivoOriginal
            );

            return $this->json([
                'resultado' => $resultado,
                'facturaProveedorId' => $facturaProveedor->getId(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Error procesando la factura: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}', name: 'factura_proveedor_show', methods: ['GET'])]
    public function show(FacturaProveedor $factura): Response
    {
        return $this->render('factura_proveedor/show.html.twig', [
            'factura' => $factura,
        ]);
    }

    #[Route('/{id}/revisar', name: 'factura_proveedor_revisar', methods: ['GET', 'POST'])]
    public function revisar(
        FacturaProveedor $factura,
        Request $request
    ): Response {
        if ($request->isMethod('POST')) {
            $accion = $request->request->get('accion');
            $lineaId = $request->request->getInt('linea_id');
            $lineasPost = $request->request->all('lineas');
           

            foreach ($factura->getLineas() as $linea) {
                $id = $linea->getId();

                if ($lineaId && $id !== $lineaId) {
                    continue;
                }

                if (!isset($lineasPost[$id])) {
                    continue;
                }

                $datos = $lineasPost[$id];

                $bruto = $this->toFloat($datos['importeBruto'] ?? 0);
                $ivaPct = $this->toFloat($datos['porcentajeIva'] ?? 21);
                $tieneRe = isset($datos['tieneRecargoEquivalencia']);
                $rePct = $tieneRe ? $this->toFloat($datos['porcentajeRecargoEquivalencia'] ?? 5.2) : 0;

                $importeIva = round($bruto * ($ivaPct / 100), 2);
                $importeRe = $tieneRe ? round($bruto * ($rePct / 100), 2) : 0;
                $total = round($bruto + $importeIva + $importeRe, 2);

                $linea->setDescripcion($datos['descripcion'] ?? null);
                $linea->setCantidad($this->toFloat($datos['cantidad'] ?? 1));
                $linea->setPrecioUnitario($this->toFloat($datos['precioUnitario'] ?? 0));
                $linea->setImporteBruto($bruto);
                $linea->setBase($bruto);
                $linea->setPorcentajeIva($ivaPct);
                $linea->setImporteIva($importeIva);
                $linea->setTieneRecargoEquivalencia($tieneRe);
                $linea->setPorcentajeRecargoEquivalencia($tieneRe ? $rePct : null);
                $linea->setImporteRecargoEquivalencia($tieneRe ? $importeRe : null);
                $linea->setTotal($total);

                if ($accion === 'confirmar_linea') {
                    $linea->setEstado('pendiente');
                }

                if ($accion === 'ignorar_linea') {
                    $linea->setEstado('ignorada');
                }
            }

            $factura->setEstadoAsignacion($this->calcularEstadoFacturaProveedor($factura));

            $this->em->flush();

            return $this->redirectToRoute('factura_proveedor_revisar', [
                'id' => $factura->getId(),
            ]);
        }

        return $this->render('factura_proveedor/revisar.html.twig', [
            'factura' => $factura,
        ]);
    }

    #[Route('/{id}/asignar', name: 'factura_proveedor_asignar', methods: ['GET', 'POST'])]
    public function asignar(
        FacturaProveedor $factura,
        Request $request,
        ProyectoRepository $proyectoRepository,
        FacturaProveedorRepository $facturaProveedorRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $lineasPost = $request->request->all('lineas');

            foreach ($factura->getLineas() as $linea) {
                if (!in_array($linea->getEstado(), ['pendiente', 'parcial'], true)) {
                    continue;
                }

                $lineaId = $linea->getId();

                if (!isset($lineasPost[$lineaId])) {
                    continue;
                }

                $datos = $lineasPost[$lineaId];

                $tipoDestino = $datos['tipo_destino'] ?? null;
                $proyectoId = $datos['proyecto_id'] ?? null;
                $cantidadAsignada = $this->toFloat($datos['cantidad_asignada'] ?? 0);

                if (!$tipoDestino || $cantidadAsignada <= 0) {
                    continue;
                }

                $cantidadLinea = max((float) $linea->getCantidad(), 1);
                $totalLinea = (float) $linea->getTotal();

                $cantidadYaAsignada = 0.0;

                foreach ($linea->getAsignaciones() as $asignacionExistente) {
                    $cantidadYaAsignada += (float) $asignacionExistente->getCantidad();
                }

                $cantidadDisponible = max($cantidadLinea - $cantidadYaAsignada, 0);

                if ($cantidadDisponible <= 0) {
                    $linea->setEstado('asignada');
                    continue;
                }

                if ($cantidadAsignada > $cantidadDisponible) {
                    $cantidadAsignada = $cantidadDisponible;
                }

                $precioUnidad = $totalLinea / $cantidadLinea;
                $importeAsignado = round($precioUnidad * $cantidadAsignada, 2);

                $asignacion = new FacturaProveedorLineaAsignacion();
                $asignacion->setLinea($linea);
                $asignacion->setCantidad($cantidadAsignada);
                $asignacion->setImporte((string) number_format($importeAsignado, 2, '.', ''));
                $asignacion->setTipoDestino($tipoDestino);
                $asignacion->setEstado('aplicada');

                if ($tipoDestino === 'obra') {
                    if (!$proyectoId) {
                        continue;
                    }

                    $proyecto = $proyectoRepository->find($proyectoId);

                    if (!$proyecto) {
                        continue;
                    }

                    $gasto = new ProyectoGasto();
                    $gasto->setProyecto($proyecto);
                    $gasto->setCategoria('materiales');
                    $gasto->setConcepto(
                        ($factura->getProveedorNombre() ?: 'Proveedor') .
                        ' - ' .
                        ($linea->getDescripcion() ?: 'Línea factura')
                    );
                    $gasto->setProveedor($factura->getProveedorNombre());
                    $gasto->setFechaPrevista($factura->getFechaFactura() ?: new \DateTime());
                    $gasto->setImportePrevisto((string) number_format($importeAsignado, 2, '.', ''));
                    $gasto->setEstado('confirmado');
                    $gasto->setGeneraForecast(false);
                    $gasto->setNotas(
                        'Generado desde factura proveedor ' .
                        ($factura->getNumeroFactura() ?: 'sin número') .
                        ' · Línea: ' . ($linea->getDescripcion() ?: '-') .
                        ' · Cantidad asignada: ' . $cantidadAsignada .
                        ' de ' . $cantidadLinea
                    );

                    $this->em->persist($gasto);

                    $asignacion->setProyecto($proyecto);
                    $asignacion->setProyectoGasto($gasto);
                }

                $this->em->persist($asignacion);

                $nuevaCantidadAsignada = $cantidadYaAsignada + $cantidadAsignada;

                if ($nuevaCantidadAsignada >= $cantidadLinea) {
                    $linea->setEstado('asignada');
                } else {
                    $linea->setEstado('parcial');
                }
            }

            $factura->setEstadoAsignacion($this->calcularEstadoAsignacionFactura($factura));

            $this->em->flush();

            $this->addFlash('success', 'Asignaciones guardadas y gastos generados.');

            $accion = $request->request->get('accion');

            if ($accion === 'guardar_y_volver') {
                return $this->redirectToRoute('factura_proveedor_index');
            }

            if ($accion === 'guardar_y_siguiente') {
                $siguiente = $facturaProveedorRepository->findSiguientePendiente($factura->getId());

                if ($siguiente) {
                    return $this->redirectToRoute('factura_proveedor_asignar', [
                        'id' => $siguiente->getId(),
                    ]);
                }

                $this->addFlash('info', 'No quedan más facturas pendientes.');

                return $this->redirectToRoute('factura_proveedor_index');
            }

            return $this->redirectToRoute('factura_proveedor_asignar', [
                'id' => $factura->getId(),
            ]);
        }

        return $this->render('factura_proveedor/asignar.html.twig', [
            'factura' => $factura,
            'proyectos' => $proyectoRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/{id}/pdf', name: 'factura_proveedor_pdf', methods: ['GET'])]
    public function verPdf(FacturaProveedor $factura): Response
    {
        if (!$factura->getRutaPdf()) {
            throw $this->createNotFoundException('Esta factura no tiene PDF asociado.');
        }

        $rutaAbsoluta = $this->getParameter('kernel.project_dir') . '/' . $factura->getRutaPdf();

        if (!file_exists($rutaAbsoluta)) {
            throw $this->createNotFoundException('No se encuentra el PDF.');
        }

        return $this->file(
            $rutaAbsoluta,
            $factura->getNombreArchivoOriginal() ?: 'factura.pdf',
            ResponseHeaderBag::DISPOSITION_INLINE
        );
    }

    #[Route('/{id}/marcar-revisada', name: 'factura_proveedor_marcar_revisada', methods: ['POST'])]
    public function marcarRevisada(FacturaProveedor $factura): Response
    {
        $factura->setEstadoAsignacion('revisada');
        $this->em->flush();

        $this->addFlash('success', 'Factura marcada como revisada.');

        return $this->redirectToRoute('factura_proveedor_show', [
            'id' => $factura->getId(),
        ]);
    }

    #[Route('/{id}/eliminar', name: 'factura_proveedor_eliminar', methods: ['POST'])]
    public function eliminar(FacturaProveedor $factura): Response
    {
        $this->em->remove($factura);
        $this->em->flush();

        $this->addFlash('success', 'Factura eliminada correctamente.');

        return $this->redirectToRoute('factura_proveedor_index');
    }

    #[Route('/forecast/insertar', name: 'factura_proveedor_forecast_insertar', methods: ['POST'])]
    public function insertarForecast(
        Request $request,
        TiposmovimientoRepository $tiposRepo
    ): Response {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['vencimientos']) || empty($data['concepto'])) {
            return $this->json(['error' => 'Datos incompletos.'], 400);
        }

        $tipo = $tiposRepo->findOneBy(['descripcionTm' => 'Proveedor']);

        if (!$tipo) {
            return $this->json(['error' => 'No se encontró el tipo de movimiento "Proveedor".'], 404);
        }

        $insertados = 0;

        foreach ($data['vencimientos'] as $venc) {
            if (empty($venc['fecha']) || !isset($venc['importe'])) {
                continue;
            }

            $forecast = new Forecast();
            $forecast->setTipoFr($tipo);
            $forecast->setConceptoFr($data['concepto']);
            $forecast->setFechaFr(new \DateTime($venc['fecha']));
            $forecast->setImporteFr((float) $venc['importe'] * -1);
            $forecast->setOrigenFr('Banco');

            $this->em->persist($forecast);
            $insertados++;
        }

        if ($insertados === 0) {
            return $this->json(['error' => 'No hay vencimientos válidos para insertar.'], 400);
        }

        $this->em->flush();

        return $this->json(['ok' => true, 'insertados' => $insertados]);
    }

    #[Route('/productos/insertar', name: 'factura_proveedor_productos_insertar', methods: ['POST'])]
    public function insertarProductos(
        Request $request,
        TipoproductoRepository $tipoRepo
    ): Response {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['articulos'])) {
            return $this->json(['error' => 'No hay artículos.'], 400);
        }

        $insertados = 0;

        foreach ($data['articulos'] as $art) {
            if (empty($art['descripcion'])) {
                continue;
            }

            $tipo = $tipoRepo->find($art['tipo_id'] ?? 1);

            if (!$tipo) {
                continue;
            }

            $producto = new Productos();
            $producto->setDescripcionPd($art['descripcion']);

            $coste = round((float) $art['coste_con_iva'], 2);
            $pvpRaw = $coste * 1.50;
            $pvp = floor($pvpRaw) + 0.95;

            if ($pvp - $pvpRaw > 1) {
                $pvp -= 1.0;
            }

            $producto->setPrecioPd($coste);
            $producto->setPvpPd($pvp);
            $producto->setStockPd((int) $art['cantidad']);
            $producto->setFecAltaPd(new \DateTime());
            $producto->setTipoPdId($tipo);
            $producto->setObsoleto(false);

            $this->em->persist($producto);
            $insertados++;
        }

        if ($insertados === 0) {
            return $this->json(['error' => 'No hay artículos válidos.'], 400);
        }

        $this->em->flush();

        return $this->json(['ok' => true, 'insertados' => $insertados]);
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function calcularEstadoFacturaProveedor(FacturaProveedor $factura): string
    {
        $total = 0;
        $revision = 0;
        $pendiente = 0;
        $ignorada = 0;

        foreach ($factura->getLineas() as $linea) {
            $total++;

            if ($linea->getEstado() === 'revision') {
                $revision++;
            } elseif ($linea->getEstado() === 'pendiente') {
                $pendiente++;
            } elseif ($linea->getEstado() === 'ignorada') {
                $ignorada++;
            }
        }

        if ($total === 0) {
            return 'revision';
        }

        if ($revision > 0) {
            return 'revision';
        }

        if ($pendiente > 0) {
            return 'pendiente';
        }

        return 'ignorada';
    }

    private function calcularEstadoAsignacionFactura(FacturaProveedor $factura): string
    {
        $hayPendientes = false;
        $hayParciales = false;
        $hayAsignadas = false;

        foreach ($factura->getLineas() as $linea) {
            if ($linea->getEstado() === 'pendiente') {
                $hayPendientes = true;
            }

            if ($linea->getEstado() === 'parcial') {
                $hayParciales = true;
            }

            if ($linea->getEstado() === 'asignada') {
                $hayAsignadas = true;
            }
        }

        if ($hayPendientes || $hayParciales) {
            return $hayAsignadas ? 'parcial' : 'pendiente';
        }

        return 'asignada';
    }
}