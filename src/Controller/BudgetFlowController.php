<?php

namespace App\Controller;

use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class BudgetFlowController extends AbstractController
{
    public function __construct(
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
    ) {
    }

    #[Route(
        '/admin/budget-flow/configurador/{codigo}',
        name: 'budget_flow_configurador',
        methods: ['GET', 'POST']
    )]
    public function configurador(
        string $codigo,
        Request $request
    ): Response {
        $configurador = $this->budgetFlowConfiguratorService
            ->obtenerConfiguradorParaFormulario($codigo);


        $valores = [];
        $validacion = null;
        $resultado = null;

        if ($request->isMethod('POST')) {

            $valores = $request->request->all('valores');

            $valores = $this->budgetFlowConfiguratorService
                ->normalizarValores(
                    $configurador,
                    $valores
                );

            $validacion = $this->budgetFlowConfiguratorService
                ->validar(
                    $configurador,
                    $valores
                );

            if ($validacion['valido'] ?? false) {

                $resultado = $this->budgetFlowConfiguratorService
                    ->generar(
                        $configurador,
                        $valores
                    );
            }
        }

        return $this->render('budget_flow/configurador.html.twig', [
            'configurador' => $configurador,
            'valores' => $valores,
            'validacion' => $validacion,
            'resultado' => $resultado,
        ]);
    }
}