<?php

namespace App\Controller;

use App\Service\Documento\DocumentoCrearService;
use App\Service\Documento\DocumentoVerService;
use App\Service\Documento\DocumentoEstadoService;
use App\Service\Documento\DocumentoManoObraService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ClientesRepository;
use App\Repository\ProyectoRepository;
use App\Service\Documento\DocumentoCabeceraService;
use App\Service\Documento\DocumentoAccionesService;
use App\Repository\ProductosRepository;
use App\Service\Documento\DocumentoLineaService;
use App\Repository\DocumentoRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Documento;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\DocumentoLinea;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\TipoManoObraRepository;
use App\Repository\TextoManoObraRepository;

/**
 * @Route("/admin/documento")
 */
class DocumentoController extends AbstractController
{

    /**
     * Crear documento y redirigir a su vista
     *
     * @Route("/nuevo", name="app_documento_nuevo", methods={"GET"})
     */
    public function nuevo(DocumentoCrearService $crearService): Response
    {
        $documento = $crearService->crearDocumento('presupuesto');



        return $this->redirectToRoute('app_documento_show', [
            'id' => $documento->getId(),


        ]);
    }

    #[Route('/{id}', name: 'app_documento_show', methods: ['GET'])]
    public function show(
        int $id,
        Request $request,
        DocumentoVerService $documentoVerService,
        ClientesRepository $clientesRepository,
        ProyectoRepository $proyectoRepository,
        DocumentoAccionesService $documentoAccionesService,
        TipoManoObraRepository $tipoManoObraRepository,
        TextoManoObraRepository $textoManoObraRepository
    ): Response {
        $documento = $documentoVerService->obtenerPorId($id);
        $editarCabecera = $request->query->getBoolean('editarCabecera');
        $acciones = $documentoAccionesService->getAccionesDisponibles($documento);
        $seleccionadosManoObra = $this->construirSeleccionadosPorRelacion($documento);
        $textosManuales = $this->construirTextosManual($documento);

        return $this->render('documento/show.html.twig', [
            'documento' => $documento,
            'acciones' => $acciones,
            'editarCabecera' => $editarCabecera,
            'clientes' =>  $clientesRepository->findBy([], ['nombreCl' => 'ASC']),
            'proyectos' => $editarCabecera ? $proyectoRepository->findBy([], ['nombre' => 'ASC']) : [],
            'tiposManoObra' => $tipoManoObraRepository->findAll(),
            'textosManoObra' => $this->agruparTextos($textoManoObraRepository->findAll()),
            'seleccionadosManoObra' => $seleccionadosManoObra,
            'textosManuales' => $textosManuales,

        ]);
    }


    #[Route('/{id}/guardar-cabecera', name: 'app_documento_guardar_cabecera', methods: ['POST'])]
    public function guardarCabecera(
        int $id,
        Request $request,
        DocumentoCabeceraService $documentoCabeceraService
    ): Response {
        $documentoCabeceraService->guardarCabecera(
            $id,
            $request->request->all()
        );

        $this->addFlash('success', 'Cabecera actualizada correctamente.');

        return $this->redirectToRoute('app_documento_show', [
            'id' => $id,
        ]);
    }

    #[Route('/documento/{id}/guardar-mano-obra', name: 'app_documento_guardar_mano_obra', methods: ['POST'])]
    public function guardarManoObra(
        Request $request,
        Documento $documento,
        DocumentoManoObraService $documentoManoObraService
    ): Response {
        $selecciones = $request->request->all('selecciones');
        $textoManual = $request->request->all('textoManual');

        $documentoManoObraService->guardarDesdeSeleccion($documento, $selecciones, $textoManual);

        $this->addFlash('success', 'Mano de obra guardada correctamente.');

        return $this->redirectToRoute('app_documento_show', [
            'id' => $documento->getId(),
        ]);
    }

    private function construirSeleccionadosPorRelacion(Documento $documento): array
    {
        $out = [];

        foreach ($documento->getManoObra() as $mo) {
            $tipo = $mo->getCategoriaMo();
            if (!$tipo) continue;

            $tipoId = $tipo->getId();
            foreach ($mo->getSeleccionesTexto() as $sel) {
                $out[$tipoId][] = $sel->getTextoManoObra()->getId();
            }
        }

        return $out;
    }    

    // ── Buscador AJAX de productos ──────────────────────────────
    #[Route('/productos/buscar', name: 'app_productos_buscar', methods: ['GET'])]
    public function buscarProductos(
        Request $request,
        ProductosRepository $productosRepository
    ): JsonResponse {
        $q = trim($request->query->get('q', ''));

        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $productos = $productosRepository->findBySearchQuery($q);

        $data = array_map(fn($p) => [
            'id'     => $p->getId(),
            'nombre' => $p->getDescripcionPd(),
            'pvp'    => $p->getPvpPd(),
            'stock'  => $p->getStockPd(),
            'tipo'   => $p->getTipoPdId()?->getDecripcionTp() ?? '—',
        ], $productos);

        return $this->json($data);
    }

    // ── Guardar línea ───────────────────────────────────────────
    #[Route('/documento/{id}/linea/guardar', name: 'app_documento_guardar_linea', methods: ['POST'])]
    public function guardarLinea(
        Request $request,
        Documento $documento,
        DocumentoLineaService $lineaService
    ): Response {
        $lineaService->crearLinea(
            documento:   $documento,
            descripcion: trim($request->request->get('descripcion', '')),
            cantidad:    (float) $request->request->get('cantidad', 1),
            precio:      (float) $request->request->get('precioUnitario', 0),
            descuento:   (float) $request->request->get('descuento', 0),
            productoId:  $request->request->get('productoId') ?: null,
            lineaId:    (float) $request->request->get('lineaId') ?: 0,
            tipo:        $request->request->get('tipoLinea', 'producto')
        );

        $this->addFlash('success', 'Línea añadida correctamente');

        return $this->redirectToRoute('app_documento_show', ['id' => $documento->getId()]);
    }

    #[Route('/documento/linea/{id}/eliminar', name: 'app_documento_linea_eliminar', methods: ['POST'])]
    public function eliminarLinea(
        Request $request,
        DocumentoLinea $linea,
        DocumentoLineaService $lineaService
    ): Response {
        $documento = $linea->getDocumento();

        if (!$this->isCsrfTokenValid('eliminar-linea-' . $linea->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF no válido');
        }

        $lineaService->eliminarLinea($linea);

        $this->addFlash('success', 'Línea eliminada correctamente');

        return $this->redirectToRoute('app_documento_show', [
            'id' => $documento->getId(),
        ]);
    }    

    #[Route('/{id}/pdf', name: 'app_documento_pdf')]
    public function pdf(
        int $id,
        Request $request,
        DocumentoVerService $documentoVerService
    ): Response {
        $documento = $documentoVerService->obtenerPorId($id);

        $tpl = (string) $request->query->get('tpl', 'clasico');

        $plantillasPermitidas = ['clasico', 'moderno', 'obra', 'cliente'];
        if (!in_array($tpl, $plantillasPermitidas, true)) {
            $tpl = 'clasico';
        }

        $template = sprintf('documento/pdf-%s.html.twig', $tpl);

        $html = $this->renderView($template, [
            'documento' => $documento,
            'logoPath'  => $this->getParameter('kernel.project_dir') . '/public/img/logo.png',
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setChroot($this->getParameter('kernel.project_dir') . '/public');

        $dompdf = new Dompdf($options);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf']
        );
    }


    #[Route('/{id}/entregar', name: 'app_documento_entregar', methods: ['POST'])]
    public function entregar(
        int $id,
        DocumentoEstadoService $estadoService
    ): Response {
        try {
            $estadoService->marcarComoEntregado($id);
            $this->addFlash('success', 'Documento marcado como entregado.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_documento_show', ['id' => $id]);
    }

    #[Route('/{id}/aceptar', name: 'app_documento_aceptar', methods: ['POST'])]
    public function aceptar(
        int $id,
        DocumentoEstadoService $documentoEstadoService
    ): Response {
        try {
            $factura = $documentoEstadoService->aceptarYGenerarFactura($id);
            $this->addFlash('success', 'Presupuesto aceptado y factura generada correctamente.');

            return $this->redirectToRoute('app_documento_show', [
                'id' => $factura->getId(),
            ]);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_documento_show', [
                'id' => $id,
            ]);
        }
    }

    #[Route('/{id}/rechazar', name: 'app_documento_rechazar', methods: ['POST'])]
    public function rechazar(
        int $id,
        DocumentoEstadoService $estadoService
    ): Response {
        try {
            $estadoService->rechazar($id);
            $this->addFlash('success', 'Documento rechazado.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_documento_show', ['id' => $id]);
    }    

    #[Route('/', name: 'documento_index')]
    public function index(Request $request, DocumentoRepository $documentoRepo): Response
    {
        $filtros = [
            'busqueda'        => $request->query->get('busqueda', ''),
            'tipo'            => $request->query->get('tipo', ''),
            'estadoComercial' => $request->query->get('estadoComercial', ''),
            'estadoCobro'     => $request->query->get('estadoCobro', ''),
        ];

        $documentos = $documentoRepo->findByFiltros($filtros);

        return $this->render('documento/index.html.twig', [
            'documentos' => $documentos,
            'filtros'    => $filtros,
        ]);
    }    

    private function agruparTextos(array $textos): array
    {
        $resultado = [];

        foreach ($textos as $texto) {
            $tipoId = $texto->getTipoXo()->getId();
            $resultado[$tipoId][] = $texto;
        }

        return $resultado;
    }    

    private function construirTextosManual(Documento $documento): array
    {
        $out = [];

        foreach ($documento->getManoObra() as $mo) {
            if ($mo->getCategoriaMo()) {
                $out[$mo->getCategoriaMo()->getId()] = $mo->getTextoMo();
            }
        }

        return $out;
    }

    #[Route('/documento/{id}/preset/{tipo}', name: 'app_documento_mano_obra_preset')]
    public function aplicarPreset(
        Documento $documento,
        string $tipo,
        DocumentoManoObraService $service
    ): Response {
        $service->aplicarPreset($documento, $tipo);

        $this->addFlash('success', 'Configuración aplicada');

        return $this->redirectToRoute('app_documento_show', [
            'id' => $documento->getId()
        ]);
    }    


}
