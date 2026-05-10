<?php

namespace App\Controller;

use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Service\Proyecto\ProyectoCobroService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;



#[Route('/admin/proyecto/cobro')]
class ProyectoCobroController extends AbstractController
{

    #[Route('/{id}/nuevo', name: 'proyecto_cobro_nuevo', methods: ['POST'])]
    public function nuevo(
        Proyecto $proyecto,
        Request $request,
        ProyectoCobroService $cobroService
    ): Response {
        $fecha = new \DateTime($request->request->get('fecha', 'now'));

        $cobroService->registrarCobro(
            proyecto: $proyecto,
            fecha: $fecha,
            metodo: $request->request->get('metodo', 'transferencia'),
            importeBruto: (float) $request->request->get('importeBruto', 0),
            porcentajeRecargo: (float) $request->request->get('porcentajeRecargo', 0),
            importeRecargo: (float) $request->request->get('importeRecargo', 0),
            importeNeto: (float) $request->request->get('importeNeto', 0),
            referencia: $request->request->get('referencia') ?: null,
            notas: $request->request->get('notas') ?: null,
        );

        $this->addFlash('success', 'Cobro registrado correctamente.');

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyecto->getId(),
        ]);
    }

    #[Route('/{id}/nuevos', name: 'proyecto_cobro_nuevos', methods: ['POST'])]
    public function nuevos(
        Proyecto $proyecto,
        Request $request,
        ProyectoCobroService $cobroService
    ): Response {

        dd([
        'proyecto_id' => $proyecto->getId(),
        'post' => $request->request->all(),
    ]);

        try {
            $fecha = new \DateTime($request->request->get('fecha', 'now'));

            $cobroService->registrarCobro(
                proyecto: $proyecto,
                fecha: $fecha,
                metodo: $request->request->get('metodo', 'transferencia'),
                importeBruto: (float) $request->request->get('importeBruto', 0),
                porcentajeRecargo: (float) $request->request->get('porcentajeRecargo', 0),
                importeRecargo: (float) $request->request->get('importeRecargo', 0),
                importeNeto: (float) $request->request->get('importeNeto', 0),
                referencia: $request->request->get('referencia') ?: null,
                notas: $request->request->get('notas') ?: null,
            );

            $this->addFlash('success', 'Cobro registrado correctamente.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyecto->getId(),
        ]);
    }

    #[Route('/{id}/eliminar', name: 'proyecto_cobro_eliminar', methods: ['POST'])]
    public function eliminar(
        ProyectoCobro $cobro,
        Request $request,
        ProyectoCobroService $cobroService
    ): Response {
        $proyecto = $cobro->getProyecto();

        if (!$this->isCsrfTokenValid('eliminar-proyecto-cobro-' . $cobro->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF no válido.');
        }

        $cobroService->eliminarCobro($cobro);

        $this->addFlash('success', 'Cobro eliminado correctamente.');

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyecto?->getId(),
        ]);
    }

    #[Route('/{id}/justificante/pdf', name: 'proyecto_cobro_justificante_pdf', methods: ['GET'])]
    public function justificantePdf(ProyectoCobro $cobro): Response
    {
        $proyecto = $cobro->getProyecto();

        if (!$proyecto) {
            throw $this->createNotFoundException('Cobro sin proyecto asociado.');
        }

        $presupuesto = null;

        foreach ($proyecto->getDocumentos() as $documento) {
            if ($documento->getTipoDocumento() === 'presupuesto') {
                $presupuesto = $documento;
                break;
            }
        }

        $html = $this->renderView('proyecto/justificante_cobro_pdf.html.twig', [
            'proyecto' => $proyecto,
            'cobro' => $cobro,
            'cliente' => $proyecto->getCliente(),
            'presupuesto' => $presupuesto,
        ]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf(
            'justificante-cobro-proyecto-%s-cobro-%s.pdf',
            $proyecto->getId(),
            $cobro->getId()
        );

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }    
}