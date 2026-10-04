<?php

namespace App\Service\WebPresupuesto;

use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use Psr\Log\LoggerInterface;

class BudgetFlowWebDuchaProvider implements WebDuchaBudgetFlowProviderInterface
{
    public function __construct(
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        private readonly LoggerInterface $logger,
        private readonly string $configuradorCodigo = 'web_ducha',
    ) {
    }

    public function obtenerConfigurador(): array
    {
        return $this->budgetFlowConfiguratorService
            ->obtenerConfiguradorParaFormulario($this->configuradorCodigo);
    }

    public function obtenerDefinicion(): ?array
    {
        try {
            return $this->budgetFlowConfiguratorService
                ->obtenerDefinicionParaConversacion($this->configuradorCodigo);
        } catch (\Throwable $e) {
            $this->logger->error('BudgetFlow no disponible al obtener definicion de configurador web.', [
                'configurador' => $this->configuradorCodigo,
                'exception' => $e,
            ]);

            return null;
        }
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
