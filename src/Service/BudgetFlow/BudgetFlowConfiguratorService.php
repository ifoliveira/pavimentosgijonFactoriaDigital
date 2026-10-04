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

    public function obtenerDefinicion(string $codigo): array
    {
        return $this->budgetFlowClient->obtenerDefinicion($codigo);
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
            $this->construirSolicitudApi($configurador, $valores)
        );
    }


    public function generar(
        array $configurador,
        array $valores
    ): array {

        return $this->budgetFlowClient->generar(
            $configurador['codigo'],
            $this->construirSolicitudApi($configurador, $valores)
        );
    }

    public function construirSolicitudApi(
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

            $configuradorComponente = $componente['configurador'] ?? $componente;

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

            if (!$codigo) {
                continue;
            }

            $valorInformado = array_key_exists($codigo, $valores);

            if (!$valorInformado && !$this->permiteValorDefectoAutomatico($campo)) {
                continue;
            }

            $valor = $valorInformado
                ? $valores[$codigo]
                : ($campo['valorDefecto'] ?? null);

            $valorNormalizado = $this->normalizarValor(
                $valor,
                $campo['tipo'] ?? 'texto'
            );

            if ($this->valorNoInformado($valorNormalizado)) {
                continue;
            }

            $resultado[$codigo] = $valorNormalizado;
        }

        return $resultado;
    }    

    private function permiteValorDefectoAutomatico(array $campo): bool
    {
        if (!array_key_exists('valorDefecto', $campo)) {
            return false;
        }

        if (!array_key_exists('origenValor', $campo) || $campo['origenValor'] === null) {
            return true;
        }

        return in_array($campo['origenValor'], ['fijo', 'interno'], true);
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

    public function obtenerDefinicionParaConversacion(string $codigo): array
    {
        $definicion = $this->obtenerDefinicion($codigo);
        $definicion['codigo'] = $definicion['codigo'] ?? $codigo;
        $definicion['tipo'] = $definicion['tipo'] ?? 'compuesto';
        $definicion['__fuente_estructura'] = 'definicion';

        return $definicion;
    }

}
