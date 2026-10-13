<?php

namespace App\Controller;

use App\Repository\ProyectoRepository;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoGastoRepository;
use App\Entity\Clientes;
use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Form\ProyectoGastoType;
use App\Service\CestaUserService;
use App\Form\ProyectoType;
use App\Entity\Documento;
use App\Entity\TelegramPresupuestoRecepcion;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use App\Service\Documento\BudgetFlowDocumentoService;
use App\Service\Documento\DocumentoCrearService;
use App\Service\FichaVisita\FichaVisitaAiExtractorInterface;
use App\Service\FichaVisita\FichaVisitaConjuntoDuchaAdapter;
use App\Service\FichaVisita\PresupuestoDesdeFotoService;
use App\Service\FichaVisita\FichaVisitaStorage;
use App\Service\FichaVisita\TelefonoMatcher;
use App\Service\Proyecto\ProyectoPresupuestoFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ProyectoGasto\ProyectoGastoService;

#[Route('/admin/proyecto')]
class ProyectoController extends AbstractController
{
    #[Route('/nuevo', name: 'app_proyecto_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProyectoPresupuestoFactory $proyectoPresupuestoFactory): Response
    {
        $proyecto = new Proyecto();

        $form = $this->createForm(ProyectoType::class, $proyecto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tipoObra = $request->request->get('tipo_obra');
            $resultado = $proyectoPresupuestoFactory->crearDesdeProyecto(
                $proyecto,
                is_string($tipoObra) ? $tipoObra : null
            );

            $this->addFlash('success', 'Proyecto y presupuesto inicial creados correctamente.');

            return $this->redirectToRoute('app_proyecto_show', [
                'id' => $resultado['proyecto']->getId(),
            ]);
        }

        return $this->render('proyecto/new.html.twig', [
            'proyecto' => $proyecto,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/desde-foto', name: 'app_proyecto_desde_foto', methods: ['GET', 'POST'])]
    public function desdeFoto(
        Request $request,
        FichaVisitaStorage $storage,
        FichaVisitaAiExtractorInterface $extractor,
        TelefonoMatcher $telefonoMatcher,
        FichaVisitaConjuntoDuchaAdapter $conjuntoDuchaAdapter,
        BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        BudgetFlowDocumentoService $budgetFlowDocumentoService,
        ProyectoPresupuestoFactory $proyectoPresupuestoFactory,
        PresupuestoDesdeFotoService $presupuestoDesdeFotoService,
        EntityManagerInterface $em
    ): Response {
        $viewData = $this->emptyDesdeFotoViewData($request);

        if ($request->isMethod('POST')) {
            if ($this->superaPostMaxSize($request)) {
                $viewData['error'] = sprintf(
                    'La imagen es demasiado grande para subirla. El límite actual del formulario es %s; reduce la foto y vuelve a intentarlo.',
                    $this->formatBytes($this->iniBytes((string) ini_get('post_max_size')))
                );
                $viewData['obra'] = trim((string) $request->request->get('obra', 'Cambio de bañera por ducha'));
                $viewData['descripcion'] = trim((string) $request->request->get('descripcion', ''));

                return $this->render('proyecto/desde_foto.html.twig', $viewData);
            }

            if (!$this->isCsrfTokenValid('proyecto_desde_foto', (string) $request->request->get('_token'))) {
                $viewData['error'] = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';

                return $this->render('proyecto/desde_foto.html.twig', $viewData);
            }

            $obra = trim((string) $request->request->get('obra', 'Cambio de bañera por ducha'));
            $descripcion = trim((string) $request->request->get('descripcion', ''));
            $phase = (string) $request->request->get('_phase', 'upload');
            $filename = basename((string) $request->request->get('foto_filename', ''));
            $datos = [];

            try {
                if ($phase === 'upload') {
                    $foto = $request->files->get('foto');

                    if (!$foto) {
                        throw new \RuntimeException('Sube una fotografía de la ficha para continuar.');
                    }

                    $error = $storage->validar($foto);
                    if ($error !== null) {
                        throw new \RuntimeException($error);
                    }

                    $filename = $storage->guardarTemporal($foto);
                    $mimeType = $storage->mimeType($filename);
                    $datos = $extractor->extraer($storage->absolutePath($filename), $mimeType)->toArray();
                } else {
                    $datosPrevios = json_decode((string) $request->request->get('datos_json', '{}'), true);
                    $datosPrevios = is_array($datosPrevios) ? $datosPrevios : [];
                    $datos = [
                        ...$datosPrevios,
                        'nombre_cliente' => trim((string) $request->request->get('nombre_cliente')) ?: null,
                        'telefono' => trim((string) $request->request->get('telefono')) ?: null,
                        'direccion_obra' => trim((string) $request->request->get('direccion_obra')) ?: null,
                        'pendientes' => [],
                    ];
                }

                $decision = $this->resolverFichaVisita(
                    $request,
                    $datos,
                    $filename,
                    $obra,
                    $descripcion,
                    $telefonoMatcher,
                    $conjuntoDuchaAdapter,
                    $budgetFlowConfiguratorService,
                    $budgetFlowDocumentoService,
                    $proyectoPresupuestoFactory,
                    $presupuestoDesdeFotoService,
                    $em,
                    $storage
                );

                if ($decision instanceof Response) {
                    return $decision;
                }

                return $this->render('proyecto/desde_foto.html.twig', $decision);
            } catch (\Throwable $e) {
                $viewData = $this->emptyDesdeFotoViewData($request);
                $viewData['error'] = $e->getMessage();
                $viewData['obra'] = $obra;
                $viewData['descripcion'] = $descripcion;
                $viewData['foto_filename'] = $filename ?: null;

                return $this->render('proyecto/desde_foto.html.twig', $viewData);
            }
        }

        return $this->render('proyecto/desde_foto.html.twig', $viewData);
    }

    #[Route('/desde-foto/preview/{filename}', name: 'app_proyecto_desde_foto_preview', methods: ['GET'])]
    public function previewFichaFoto(string $filename, FichaVisitaStorage $storage): Response
    {
        $path = $storage->absolutePath($filename);

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path);
    }

    #[Route('/desde-foto/telegram/{id<\d+>}/completar', name: 'app_proyecto_desde_foto_telegram_completar', methods: ['GET', 'POST'])]
    public function completarDesdeTelegram(
        Request $request,
        TelegramPresupuestoRecepcion $recepcion,
        PresupuestoDesdeFotoService $presupuestoDesdeFotoService,
        EntityManagerInterface $em
    ): Response {
        $datos = $recepcion->getDatosExtraidos() ?? [];
        $filename = basename((string) $recepcion->getImagenPath());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('proyecto_desde_foto_telegram_'.$recepcion->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF no válido.');
            }

            $datos = [
                ...$datos,
                'nombre_cliente' => trim((string) $request->request->get('nombre_cliente')) ?: null,
                'telefono' => trim((string) $request->request->get('telefono')) ?: null,
                'direccion_obra' => trim((string) $request->request->get('direccion_obra')) ?: null,
                'pendientes' => [],
            ];

            $resultado = $presupuestoDesdeFotoService->ejecutar(
                $datos,
                'Cambio de bañera por ducha',
                '',
                is_string($datos['direccion_obra'] ?? null) ? $datos['direccion_obra'] : null,
                $recepcion->getImagenPath(),
                ((int) $request->request->get('cliente_id', 0)) ?: null
            );

            $recepcion
                ->setDatosExtraidos($resultado['datos'] ?? $datos)
                ->setAdaptacion($resultado['adaptacion'] ?? null)
                ->setProyecto($resultado['proyecto'] ?? null)
                ->setDocumento($resultado['documento'] ?? null);

            if (in_array($resultado['status'], [PresupuestoDesdeFotoService::PENDIENTE_CLIENTE, PresupuestoDesdeFotoService::CLIENTE_AMBIGUO], true)) {
                $recepcion->setEstado(TelegramPresupuestoRecepcion::ESTADO_PENDIENTE_CLIENTE);
                $em->flush();

                return $this->render('proyecto/desde_foto.html.twig', [
                    ...$this->desdeFotoReviewData('Cambio de bañera por ducha', '', $filename, $resultado['datos'], $resultado['candidatos'] ?? [], $resultado['warning'] ?? 'Completa los datos pendientes.'),
                    'telegramRecepcion' => $recepcion,
                ]);
            }

            if (in_array($resultado['status'], [PresupuestoDesdeFotoService::REVISION_TECNICA, PresupuestoDesdeFotoService::BUDGETFLOW_ERROR], true)) {
                $recepcion->setEstado(TelegramPresupuestoRecepcion::ESTADO_REVISION_TECNICA);
                $em->flush();

                return $this->redirectToRoute('app_proyecto_desde_foto_revisar', ['id' => $resultado['documento']->getId()]);
            }

            $recepcion->setEstado(TelegramPresupuestoRecepcion::ESTADO_COMPLETADA);
            $em->flush();

            return $this->redirectToRoute('app_documento_show', ['id' => $resultado['documento']->getId()]);
        }

        return $this->render('proyecto/desde_foto.html.twig', [
            ...$this->desdeFotoReviewData('Cambio de bañera por ducha', '', $filename, $datos, [], 'Completa los datos de cliente para continuar con esta recepción.'),
            'telegramRecepcion' => $recepcion,
        ]);
    }

    #[Route('/gasto/{id}/editar',name: 'app_proyecto_gasto_edit',methods: ['GET', 'POST'] )]
    public function edit(
        ProyectoGasto $gasto,
        Request $request,
        ProyectoGastoService $proyectoGastoService
    ): Response {

        $proyecto = $gasto->getProyecto();

        if ($gasto->getEstado() !== ProyectoGasto::ESTADO_PREVISTO) {
            return new Response(
                'Solo se pueden editar gastos previstos.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $form = $this->createForm(ProyectoGastoType::class, $gasto, [
            'documentos_proyecto' => $proyecto->getDocumentos()->toArray(),

            'action' => $this->generateUrl(
                'app_proyecto_gasto_edit',
                ['id' => $gasto->getId()]
            ),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $proyectoGastoService->guardar(
                $gasto,
                esNuevo: false
            );

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Gasto actualizado correctamente.',
                ]);
            }

            $this->addFlash(
                'success',
                'Gasto actualizado correctamente.'
            );

            return $this->redirectToRoute('app_proyecto_show', [
                'id' => $proyecto->getId(),
            ]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('proyecto_gasto/_form_modal.html.twig', [
                'form' => $form->createView(),
                'proyecto' => $proyecto,
                'gasto' => $gasto,
                'modo' => 'editar',
            ]);
        }

        return $this->render('proyecto_gasto/edit.html.twig', [
            'form' => $form->createView(),
            'proyecto' => $proyecto,
            'gasto' => $gasto,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_proyecto_show', methods: ['GET'])]
    public function show(Proyecto $proyecto): Response
    {
        return $this->render('proyecto/show.html.twig', [
            'proyecto' => $proyecto,
        ]);
    }

    #[Route('/', name: 'app_proyecto_index', methods: ['GET'])]
    public function index(\App\Repository\ProyectoRepository $proyectoRepository): Response
    {
        return $this->render('proyecto/index.html.twig', [
            'proyectos' => $proyectoRepository->findBy([], ['id' => 'DESC']),
        ]);
    }    

    #[Route('/{id}/gastos-pendientes', name: 'app_proyecto_gastos_pendientes',methods: ['GET'])]
    public function gastosPendientes(
        Proyecto $proyecto
    ): Response {
        return $this->render(
            'proyecto_gasto/_modal_pendientes.html.twig',
            [
                'proyecto' => $proyecto,
                'gastos' => $proyecto->getGastos(),
            ]
        );
    }

    #[Route('/{id}/justificantes',
        name: 'app_proyecto_justificantes',
        methods: ['GET']
    )]
    public function justificantes(
        Proyecto $proyecto
    ): Response {
        return $this->render(
            'proyecto_gasto/_modal_justificantes.html.twig',
            [
                'proyecto' => $proyecto,
                'cobros' => $proyecto->getCobros(),
            ]
        );
    }    

    #[Route(
        '/{id}/presupuesto-resumen',
        name: 'app_proyecto_presupuesto_resumen',
        methods: ['GET']
    )]
    public function presupuestoResumen(
        Proyecto $proyecto,
        DocumentoRepository $documentoRepository
    ): Response {

        $presupuesto = $documentoRepository
            ->findPresupuestoInicialDeProyecto($proyecto);

        if (!$presupuesto) {
            return $this->render(
                'proyecto/_modal_presupuesto.html.twig',
                [
                    'proyecto' => $proyecto,
                    'presupuesto' => null,
                ]
            );
        }

        return $this->render(
            'proyecto/_modal_presupuesto.html.twig',
            [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
            ]
        );
    }

    #[Route('/{id<\d+>}/crear-presupuesto-inicial', name: 'app_proyecto_crear_presupuesto_inicial', methods: ['POST'])]
        public function crearPresupuestoInicial(
            Proyecto $proyecto,
            DocumentoCrearService $documentoCrearService,
            EntityManagerInterface $em
        ): Response {
            foreach ($proyecto->getDocumentos() as $documentoExistente) {
                if ($documentoExistente->getTipoDocumento() === 'presupuesto') {
                    $this->addFlash('warning', 'Este proyecto ya tiene un presupuesto creado.');
                    return $this->redirectToRoute('app_proyecto_show', [
                        'id' => $proyecto->getId(),
                    ]);
                }
            }

            $clienteId = $proyecto->getCliente()?->getId();

            $documento = $documentoCrearService->crearDocumento('presupuesto', $clienteId);

            $documento->setProyecto($proyecto);

            $em->flush();

            $this->addFlash('success', 'Presupuesto inicial creado correctamente.');

            return $this->redirectToRoute('app_documento_show', [
                'id' => $documento->getId(),
            ]);
        }

    #[Route('/proyectoGasto/{id}/nuevo', name: 'app_proyecto_gasto_new', methods: ['GET', 'POST'])]
    public function newGasto(
        Proyecto $proyecto,
        Request $request,
        EntityManagerInterface $entityManager,
        ProyectoGastoService $proyectoGastoService
    ): Response {
        $gasto = new ProyectoGasto();
        $gasto->setProyecto($proyecto);

        $form = $this->createForm(ProyectoGastoType::class, $gasto, [
            'documentos_proyecto' => $proyecto->getDocumentos()->toArray(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $gasto->marcarActualizado();
            $gasto->setOrigen(ProyectoGasto::ORIGEN_MANUAL);

            $entityManager->persist($gasto);
            $proyectoGastoService->sincronizarForecastSiProcede($gasto);
            $entityManager->flush();
            $proyectoGastoService->recalcularProyecto($proyecto);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Gasto creado correctamente.',
                ]);
            }

            $this->addFlash('success', 'Gasto añadido correctamente.');

            return $this->redirectToRoute('app_proyecto_show', [
                'id' => $proyecto->getId(),
            ]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('proyecto_gasto/_form_modal.html.twig', [
                'form' => $form->createView(),
                'proyecto' => $proyecto,
                'modo' => 'nuevo',
            ]);
        }

        return $this->render('proyecto/show.html.twig', [
            'form' => $form->createView(),
            'proyecto' => $proyecto,
        ]);
    }

    #[Route('/{id}', name: 'app_proyecto_delete', methods: ['POST'])]
    public function delete(Request $request, Proyecto $proyecto, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$proyecto->getId(), $request->request->get('_token'))) {

            if ($proyecto->getTotalCobrado() > 0) {
                $this->addFlash('error', 'No puedes borrar un proyecto con cobros.');
                return $this->redirectToRoute('app_proyecto_index');
            }

            $em->remove($proyecto);
            $em->flush();

            $this->addFlash('success', 'Proyecto eliminado correctamente.');
        }

        return $this->redirectToRoute('app_proyecto_index');
    }    


    #[Route('/cerrados', name: 'app_proyecto_cerrados', methods: ['GET'])]
    public function cerrados(
        ProyectoRepository $proyectoRepository,
        DocumentoRepository $documentoRepository,
        ProyectoGastoRepository $proyectoGastoRepository
    ): Response {
        $proyectos = $proyectoRepository->findAll();

        $cerrados = [];

        foreach ($proyectos as $proyecto) {
            $factura = $documentoRepository->findFacturaDeProyecto($proyecto);

            if (!$factura) {
                continue;
            }

            $estaCerrado = $proyecto->getFechaFinReal() !== null
                || $factura->getEstadoCobro() === 'cobrado';

            if (!$estaCerrado) {
                continue;
            }

            $presupuesto = $documentoRepository->findPresupuestoInicialDeProyecto($proyecto);
            $coste = $proyectoGastoRepository->sumarCosteActualPorProyecto($proyecto);
            $margen = (float) $proyecto->getTotalFacturado() - $coste;

            $cerrados[] = [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
                'factura' => $factura,
                'coste' => $coste,
                'margen' => $margen,
            ];
        }

        return $this->render('proyecto/cerrados.html.twig', [
            'cerrados' => $cerrados,
        ]);
    }

    #[Route(
        '/{id}/gastos-previstos',
        name: 'app_proyecto_gastos_previstos',
        methods: ['GET']
    )]
    public function gastosPrevistos(
        Proyecto $proyecto,
        ProyectoGastoRepository $proyectoGastoRepository
    ): JsonResponse {

        $gastos = $proyectoGastoRepository->findBy(
            [
                'proyecto' => $proyecto,
                'estado' => ProyectoGasto::ESTADO_PREVISTO,
            ],
            [
                'fechaPrevista' => 'ASC',
            ]
        );

        return $this->json([
            'success' => true,

            'gastos' => array_map(
                static function (ProyectoGasto $gasto): array {

                    return [
                        'id' => $gasto->getId(),
                        'concepto' => $gasto->getConcepto(),
                        'importe' => (float) $gasto->getImportePrevisto(),
                        'fecha' =>
                            $gasto->getFechaPrevista()
                                ?->format('Y-m-d'),
                        'proveedor' => $gasto->getProveedor(),
                    ];

                },
                $gastos
            ),
        ]);
    }    

    private function emptyDesdeFotoViewData(Request $request): array
    {
        return [
            'obra' => 'Cambio de bañera por ducha',
            'descripcion' => '',
            'foto_filename' => null,
            'datos' => [
                'nombre_cliente' => null,
                'telefono' => null,
                'direccion_obra' => null,
                'pendientes' => [],
            ],
            'candidatos' => [],
            'error' => null,
            'warning' => null,
            'needs_review' => false,
        ];
    }

    private function resolverFichaVisita(
        Request $request,
        array $datos,
        string $filename,
        string $obra,
        string $descripcion,
        TelefonoMatcher $telefonoMatcher,
        FichaVisitaConjuntoDuchaAdapter $conjuntoDuchaAdapter,
        BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        BudgetFlowDocumentoService $budgetFlowDocumentoService,
        ProyectoPresupuestoFactory $proyectoPresupuestoFactory,
        PresupuestoDesdeFotoService $presupuestoDesdeFotoService,
        EntityManagerInterface $em,
        FichaVisitaStorage $storage
    ): Response|array {
        if ($filename === '' || !is_file($storage->absolutePath($filename))) {
            throw new \RuntimeException('No se encuentra la fotografía conservada. Sube la ficha de nuevo.');
        }

        $clienteSeleccionadoId = (int) $request->request->get('cliente_id', 0);
        $resultado = $presupuestoDesdeFotoService->ejecutar(
            $datos,
            $obra,
            $descripcion,
            is_string($datos['direccion_obra'] ?? null) ? $datos['direccion_obra'] : null,
            $storage->relativePath($filename),
            $clienteSeleccionadoId > 0 ? $clienteSeleccionadoId : null
        );

        if (in_array($resultado['status'], [PresupuestoDesdeFotoService::PENDIENTE_CLIENTE, PresupuestoDesdeFotoService::CLIENTE_AMBIGUO], true)) {
            return $this->desdeFotoReviewData(
                $obra,
                $descripcion,
                $filename,
                $resultado['datos'],
                $resultado['candidatos'] ?? [],
                $resultado['warning'] ?? 'Completa los datos pendientes antes de crear el proyecto.'
            );
        }

        if (in_array($resultado['status'], [PresupuestoDesdeFotoService::REVISION_TECNICA, PresupuestoDesdeFotoService::BUDGETFLOW_ERROR], true)) {
            $this->addFlash('warning', $resultado['warning'] ?? 'Revisa los datos leídos de la ficha antes de generar las líneas.');
            return $this->redirectToRoute('app_proyecto_desde_foto_revisar', [
                'id' => $resultado['documento']->getId(),
            ]);
        }

        $this->addFlash('success', 'Proyecto, presupuesto y líneas creados desde la ficha.');

        return $this->redirectToRoute('app_documento_show', [
            'id' => $resultado['documento']->getId(),
        ]);
    }

    #[Route('/desde-foto/revisar/{id<\d+>}', name: 'app_proyecto_desde_foto_revisar', methods: ['GET', 'POST'])]
    public function revisarDesdeFoto(
        Request $request,
        Documento $documento,
        BudgetFlowDocumentoService $budgetFlowDocumentoService,
        FichaVisitaConjuntoDuchaAdapter $conjuntoDuchaAdapter,
        EntityManagerInterface $em
    ): Response {
        $proyecto = $documento->getProyecto();
        $datos = $proyecto?->getFichaDatosExtraidos() ?? [];
        $adaptacion = is_array($datos['budgetflow_adaptacion']['conjunto_ducha'] ?? null)
            ? $datos['budgetflow_adaptacion']['conjunto_ducha']
            : [];
        $adaptacionReparada = $conjuntoDuchaAdapter->completarGriferiaDesdeDatos($datos, $adaptacion);
        if ($adaptacionReparada !== $adaptacion) {
            $adaptacion = $adaptacionReparada;
            $datos['budgetflow_adaptacion']['conjunto_ducha'] = $adaptacion;
            if ($proyecto) {
                $proyecto->setFichaDatosExtraidos($datos);
                $em->flush();
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('proyecto_desde_foto_revisar_'.$documento->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF no válido.');
            }

            $valores = $request->request->all('valores');
            $excluidos = array_values(array_filter($request->request->all('componentes_excluidos'), 'is_string'));
            $adaptacion['componentes_excluidos'] = $excluidos;
            $adaptacion = $conjuntoDuchaAdapter->reconciliarRevision($valores, $adaptacion);
            $adaptacion['componentes_excluidos'] = $excluidos;
            $valores = $adaptacion['valores'] ?? $valores;
            $datos['budgetflow_adaptacion']['conjunto_ducha'] = $adaptacion;

            if ($proyecto) {
                $proyecto->setFichaDatosExtraidos($datos);
                $em->flush();
            }

            if ($this->requiereRevisionFicha($adaptacion)) {
                return $this->render('proyecto/desde_foto_revision.html.twig', [
                    'documento' => $documento,
                    'proyecto' => $proyecto,
                    'datos' => $datos,
                    'adaptacion' => $adaptacion,
                    'valores' => $valores,
                    'componentesExcluidos' => $excluidos,
                    'validacion' => null,
                ]);
            }

            try {
                $ejecucion = $budgetFlowDocumentoService->generarEIncorporar(
                    $documento,
                    'conjunto_ducha',
                    $valores,
                    $excluidos,
                    true
                );
            } catch (\Throwable $e) {
                $this->addFlash('error', 'No se pudieron generar las líneas: '.$e->getMessage());

                return $this->redirectToRoute('app_proyecto_desde_foto_revisar', ['id' => $documento->getId()]);
            }

            if (!($ejecucion['generado'] ?? false)) {
                return $this->render('proyecto/desde_foto_revision.html.twig', [
                    'documento' => $documento,
                    'proyecto' => $proyecto,
                    'datos' => $datos,
                    'adaptacion' => $adaptacion,
                    'valores' => $ejecucion['valores'] ?? $valores,
                    'componentesExcluidos' => $excluidos,
                    'validacion' => $ejecucion['validacion'] ?? null,
                ]);
            }

            $this->addFlash('success', 'Líneas de BudgetFlow generadas desde la ficha.');

            return $this->redirectToRoute('app_documento_show', ['id' => $documento->getId()]);
        }

        return $this->render('proyecto/desde_foto_revision.html.twig', [
            'documento' => $documento,
            'proyecto' => $proyecto,
            'datos' => $datos,
            'adaptacion' => $adaptacion,
            'valores' => $adaptacion['valores'] ?? [],
            'componentesExcluidos' => $adaptacion['componentes_excluidos'] ?? [],
            'validacion' => null,
        ]);
    }

    private function requiereRevisionFicha(array $adaptacion): bool
    {
        return ($adaptacion['pendientes'] ?? []) !== []
            || ($adaptacion['avisos'] ?? []) !== [];
    }

    private function desdeFotoReviewData(
        string $obra,
        string $descripcion,
        string $filename,
        array $datos,
        array $candidatos,
        string $warning
    ): array {
        return [
            'obra' => $obra,
            'descripcion' => $descripcion,
            'foto_filename' => $filename,
            'datos' => $datos,
            'candidatos' => $candidatos,
            'error' => null,
            'warning' => $warning,
            'needs_review' => true,
        ];
    }

    private function nombrePareceElMismo(string $nombre, Clientes $cliente): bool
    {
        $nombre = $this->normalizarNombre($nombre);
        $existente = $this->normalizarNombre(trim((string) $cliente->getNombreCl().' '.(string) $cliente->getApellidosCl()));

        if ($nombre === '' || $existente === '') {
            return false;
        }

        if ($nombre === $existente || str_contains($nombre, $existente) || str_contains($existente, $nombre)) {
            return true;
        }

        $tokensNombre = array_filter(explode(' ', $nombre), static fn (string $token): bool => strlen($token) >= 3);
        $tokensExistente = array_filter(explode(' ', $existente), static fn (string $token): bool => strlen($token) >= 3);

        return count(array_intersect($tokensNombre, $tokensExistente)) > 0;
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = strtolower(trim($nombre));
        $nombre = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre;

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $nombre));
    }

    private function superaPostMaxSize(Request $request): bool
    {
        $postMaxSize = $this->iniBytes((string) ini_get('post_max_size'));
        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);

        return $postMaxSize > 0 && $contentLength > $postMaxSize;
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $bytes = (float) $value;

        return (int) match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1, ',', ''), '0'), ',').' MB';
        }

        if ($bytes >= 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1, ',', ''), '0'), ',').' KB';
        }

        return $bytes.' bytes';
    }

}
