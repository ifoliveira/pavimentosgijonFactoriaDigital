<?php

namespace App\Controller;

use App\Repository\DocumentoRepository;
use App\Service\Documento\DocumentoCobroService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/documento')]
class CobroController extends AbstractController
{
    #[Route('/{id}/cobro/nuevo', name: 'cobro_nuevo', methods: ['POST'])]
    public function nuevo(
        int $id,
        Request $request,
        DocumentoRepository $documentoRepo,
        DocumentoCobroService $cobroService
    ): Response {
        $doc = $documentoRepo->find($id);

        if (!$doc) {
            throw $this->createNotFoundException('Documento no encontrado');
        }

        if (!$this->isCsrfTokenValid('cobro_nuevo_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF inválido.');
            return $this->redirectToRoute('documento_show', ['id' => $id]);
        }

        try {
            $cobroService->registrar(
                documento:         $doc,
                fecha:             new \DateTime($request->request->get('fecha')),
                metodo:            $request->request->get('metodo'),
                importeBruto:      $request->request->get('importeBruto'),
                porcentajeRecargo: $request->request->get('porcentajeRecargo', '0'),
                importeRecargo:    $request->request->get('importeRecargo', '0'),
                importeNeto:       $request->request->get('importeNeto', '0'),
                referencia:        $request->request->get('referencia'),
                notas:             $request->request->get('notas'),
            );

            $this->addFlash('success', 'Cobro registrado correctamente.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error al registrar el cobro: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_documento_show', ['id' => $id]);
    }

    #[Route('/cobro/{id}/borrar', name: 'cobro_delete_ajax', methods: ['GET'])]
    public function borrar(
        int $id,
        DocumentoCobroService $cobroService
    ): JsonResponse {
        try {
            $cobroService->borrar($id);
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}