<?php

namespace App\Service\BudgetFlow;

use App\Integration\BudgetFlow\BudgetFlowClient;

final class BudgetFlowConfiguratorService
{
    public function __construct(
        private readonly BudgetFlowClient $budgetFlowClient,
    ) {
    }

    public function obtenerConfigurador(string $codigo): array
    {
        return $this->budgetFlowClient->obtenerConfigurador($codigo);
    }

    public function listarConfiguradores(): array
    {
        return $this->budgetFlowClient->listarConfiguradores();
    }

    public function validar(
        array $configurador,
        array $valores
    ): array {
        return $this->budgetFlowClient->validar(
            $configurador['codigo'],
            $this->construirEntrada($configurador, $valores)
        );
    }


    public function generar(
        array $configurador,
        array $valores
    ): array {
        return $this->budgetFlowClient->generar(
            $configurador['codigo'],
            $this->construirEntrada($configurador, $valores)
        );
    }

    private function construirEntrada(
        array $configurador,
        array $valores
    ): array {
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

    public function normalizarValores(
        array $configurador,
        array $valores
    ): array {
        /*
        * Configurador simple
        */
        if (($configurador['tipo'] ?? null) !== 'compuesto') {
            return $this->normalizarCampos(
                $configurador['campos'] ?? [],
                $valores
            );
        }

        /*
        * Configurador compuesto
        */
        $resultado = [];

        foreach ($configurador['componentes'] ?? [] as $componente) {

            $codigoComponente = $componente['codigo'];

            $configuradorComponente = $componente['configurador'] ?? null;

            if (!$configuradorComponente) {
                continue;
            }

            $valoresComponente = $valores[$codigoComponente] ?? [];

            $resultado[$codigoComponente] = $this->normalizarCampos(
                $configuradorComponente['campos'] ?? [],
                $valoresComponente
            );
        }

        return $resultado;
    }

    private function normalizarCampos(
        array $campos,
        array $valores
    ): array {
        $resultado = [];

        foreach ($campos as $campo) {

            $codigo = $campo['codigo'] ?? null;

            if (!$codigo || !array_key_exists($codigo, $valores)) {
                continue;
            }

            $valorNormalizado = $this->normalizarValor(
                $valores[$codigo],
                $campo['tipo'] ?? 'texto'
            );

            if ($this->valorNoInformado($valorNormalizado)) {
                continue;
            }

            $resultado[$codigo] = $valorNormalizado;
        }

        return $resultado;
    }    

    private function normalizarValor(
        mixed $valor,
        string $tipo
    ): mixed {
        if ($this->valorNoInformado($valor)) {
            return null;
        }

        return match ($tipo) {

            'entero' => filter_var(
                $valor,
                FILTER_VALIDATE_INT,
                FILTER_NULL_ON_FAILURE
            ),

            'decimal' => is_numeric($valor)
                ? (float) $valor
                : $valor,

            'booleano' => in_array(
                $valor,
                [true, 1, '1', 'true'],
                true
            ),

            default => $valor,
        };
    }    

    private function valorNoInformado(mixed $valor): bool
    {
        return $valor === null
            || (is_string($valor) && trim($valor) === '');
    }


    public function obtenerConfiguradorParaFormulario(string $codigo): array
    {
        $configurador = $this->obtenerConfigurador($codigo);

        if (($configurador['tipo'] ?? null) !== 'compuesto') {
            return $configurador;
        }

        $componentes = [];

        foreach ($configurador['componentes'] ?? [] as $componente) {

            $componente['configurador'] = $this->obtenerConfigurador(
                $componente['codigo']
            );

            $componentes[] = $componente;
        }

        $configurador['componentes'] = $componentes;

        return $configurador;
    }

}
