<?php

namespace App\Service\WebPresupuesto;

use Psr\Log\LoggerInterface;

final class WebBanoConversationService extends WebDuchaConversationService
{
    public function __construct(
        BudgetFlowWebBanoProvider $budgetFlowProvider,
        WebPresupuestoAiClientInterface $aiClient,
        WebDuchaCampoExtractor $campoExtractor,
        LoggerInterface $logger,
        array $valoresProvisionales = [],
        array $valoresDerivados = [],
        array $conversacionIa = [],
    ) {
        parent::__construct(
            $budgetFlowProvider,
            $aiClient,
            $campoExtractor,
            $logger,
            $valoresProvisionales,
            $valoresDerivados,
            'web_baño',
            'bano',
            $conversacionIa
        );
    }
}
