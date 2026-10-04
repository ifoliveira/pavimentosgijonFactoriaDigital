<?php

namespace App\Service\WebPresupuesto;

use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use Psr\Log\LoggerInterface;

final class BudgetFlowWebBanoProvider extends BudgetFlowWebDuchaProvider
{
    public function __construct(
        BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        LoggerInterface $logger,
    ) {
        parent::__construct($budgetFlowConfiguratorService, $logger, 'web_baño');
    }
}
