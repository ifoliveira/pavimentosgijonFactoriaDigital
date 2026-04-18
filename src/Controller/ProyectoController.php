<?php

namespace App\Controller;

use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Form\ProyectoGastoType;
use App\Service\CestaUserService;
use App\Form\ProyectoType;
use App\Entity\Documento;
use App\Service\Documento\DocumentoCrearService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $proyecto = new Proyecto();

        $form = $this->createForm(ProyectoType::class, $proyecto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($proyecto);
            $entityManager->flush();

            $this->addFlash('success', 'Proyecto creado correctamente.');

            return $this->redirectToRoute('app_proyecto_show', [
                'id' => $proyecto->getId(),
            ]);
        }

        return $this->render('proyecto/new.html.twig', [
            'proyecto' => $proyecto,
            'form' => $form->createView(),
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

            $entityManager->persist($gasto);
            $entityManager->flush();

            $proyectoGastoService->sincronizarForecastSiProcede($gasto);
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
            ]);
        }

        return $this->render('proyecto_gasto/new.html.twig', [
            'form' => $form->createView(),
            'proyecto' => $proyecto,
        ]);
    }

}