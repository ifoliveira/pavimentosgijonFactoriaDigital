<?php

namespace App\Service\BudgetFlow;

use App\Integration\BudgetFlow\BudgetFlowClient;

final class PresupuestoCalculatorService
{
    public function __construct(
        private readonly BudgetFlowClient $budgetFlowClient,
    ) {
    }

    public function generar(array $datos): array
    {
        // 1. Traducir formulario ERP -> entrada BudgetFlow

        // 2. Consultar BudgetFlow

        // 3. Asignar cantidades

        // 4. Devolver líneas listas para que el Builder
        //    las incorpore al Documento
    }
}