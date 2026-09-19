<?php

namespace App\Controller;

use App\Entity\PresupuestoWeb;
use App\Repository\PresupuestoWebRepository;
use App\Service\WebPresupuesto\Admin\LeadWebBudgetFlowPresenter;
use App\Service\WebPresupuesto\Admin\LeadWebDashboardService;
use App\Service\WebPresupuesto\Admin\LeadWebTimelineBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/leads-web')]
final class AdminLeadsWebController extends AbstractController
{
    #[Route('', name: 'admin_leads_web_index', methods: ['GET'])]
    public function index(Request $request, LeadWebDashboardService $dashboardService): Response
    {
        $dashboard = $dashboardService->listar(
            (string) $request->query->get('filtro', 'requieren_intervencion'),
            trim((string) $request->query->get('q', '')),
            (string) $request->query->get('orden', 'actividad')
        );

        return $this->render('admin_leads_web/index.html.twig', [
            'dashboard' => $dashboard,
        ]);
    }

    #[Route('/{id}', name: 'admin_leads_web_show', methods: ['GET'])]
    public function show(
        int $id,
        PresupuestoWebRepository $presupuestoRepository,
        LeadWebDashboardService $dashboardService,
        LeadWebTimelineBuilder $timelineBuilder,
        LeadWebBudgetFlowPresenter $budgetFlowPresenter
    ): Response {
        $presupuesto = $presupuestoRepository->createQueryBuilder('p')
            ->leftJoin('p.lead', 'l')->addSelect('l')
            ->leftJoin('p.comunicaciones', 'c')->addSelect('c')
            ->leftJoin('p.accesos', 'a')->addSelect('a')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$presupuesto instanceof PresupuestoWeb) {
            throw $this->createNotFoundException('Lead web no encontrado.');
        }

        $detalle = $dashboardService->detalle($presupuesto);
        $timeline = $timelineBuilder->construir($presupuesto, $detalle['interacciones']);

        return $this->render('admin_leads_web/show.html.twig', [
            'detalle' => $detalle,
            'timeline' => $timeline,
            'configuracion' => $budgetFlowPresenter->configuracion($presupuesto->getJsonSolicitudBudgetFlow()),
            'presupuestoCalculado' => $budgetFlowPresenter->presupuesto($presupuesto->getJsonPresupuesto()),
            'urlCliente' => $this->generateUrl('web_presupuesto_ducha_ver', [
                'token' => $presupuesto->getToken(),
            ]),
        ]);
    }
}
