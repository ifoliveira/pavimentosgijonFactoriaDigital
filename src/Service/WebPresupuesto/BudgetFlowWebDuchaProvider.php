<?php

namespace App\Service\WebPresupuesto;

use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use Psr\Log\LoggerInterface;

final class BudgetFlowWebDuchaProvider implements WebDuchaBudgetFlowProviderInterface
{
    public function __construct(
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function obtenerConfigurador(): array
    {
        return $this->budgetFlowConfiguratorService
            ->obtenerConfiguradorParaFormulario('web_ducha');
    }

    public function validar(array $configurador, array $valores): array
    {
        $valores = $this->budgetFlowConfiguratorService
            ->normalizarValores($configurador, $valores);

        $traceId = WebDuchaDebugContext::getTraceId();
        $payload = $this->construirPayloadParaLog($configurador, $valores);

        $this->logger->debug('web_presupuesto_ducha.budgetflow.validar.payload', [
            'trace_id' => $traceId,
            'configurador' => $configurador['codigo'] ?? null,
            'payload' => $payload,
        ]);

        $resultado = $this->budgetFlowConfiguratorService
            ->validar($configurador, $valores);

        $this->logger->debug('web_presupuesto_ducha.budgetflow.validar.response', [
            'trace_id' => $traceId,
            'configurador' => $configurador['codigo'] ?? null,
            'response' => $resultado,
        ]);

        return $resultado;
    }

    public function generar(array $configurador, array $valores): array
    {
        $valores = $this->budgetFlowConfiguratorService
            ->normalizarValores($configurador, $valores);

        return $this->budgetFlowConfiguratorService
            ->generar($configurador, $valores);
    }

    private function construirPayloadParaLog(array $configurador, array $valores): array
    {
        if (($configurador['tipo'] ?? null) === 'compuesto') {
            return [
                'valores' => $valores,
            ];
        }

        return [
            'valores' => [
                $configurador['codigo'] => $valores,
            ],
        ];
    }
}
