<?php

namespace App\Service\WebPresupuesto;

final class WebDuchaCampoExtractor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function extraerCampos(array $configurador): array
    {
        if (($configurador['tipo'] ?? null) !== 'compuesto') {
            return $this->camposDeConfigurador($configurador['codigo'] ?? 'web_ducha', $configurador);
        }

        $campos = [];

        foreach ($configurador['componentes'] ?? [] as $componente) {
            $codigoComponente = $componente['codigo'] ?? null;
            $configuradorComponente = $componente['configurador'] ?? null;

            if (!$codigoComponente || !is_array($configuradorComponente)) {
                continue;
            }

            foreach ($this->camposDeConfigurador($codigoComponente, $configuradorComponente, (int) ($componente['orden'] ?? 0)) as $campo) {
                $campo['componente_obligatorio'] = (bool) ($componente['obligatorio'] ?? false);
                $campos[] = $campo;
            }
        }

        usort($campos, static fn(array $a, array $b): int => [$a['componente_orden'], $a['orden']] <=> [$b['componente_orden'], $b['orden']]);

        return $campos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function camposDeConfigurador(string $codigoComponente, array $configurador, ?int $ordenComponente = null): array
    {
        $resultado = [];

        foreach ($configurador['campos'] ?? [] as $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $codigo = $campo['codigo'] ?? null;

            if (!$codigo) {
                continue;
            }

            $resultado[] = [
                'id' => $codigoComponente.'.'.$codigo,
                'componente' => $codigoComponente,
                'codigo' => $codigo,
                'etiqueta' => $campo['etiqueta'] ?? $codigo,
                'tipo' => $campo['tipo'] ?? 'texto',
                'obligatorio' => (bool) ($campo['obligatorio'] ?? false),
                'orden' => (int) ($campo['orden'] ?? 0),
                'componente_orden' => $ordenComponente ?? (int) ($configurador['orden'] ?? 0),
                'ayuda' => $campo['ayuda'] ?? null,
                'unidad' => $this->detectarUnidad($campo),
                'opciones' => $campo['opciones'] ?? [],
                'valorDefecto' => $campo['valorDefecto'] ?? null,
                'restricciones' => $campo['restricciones'] ?? null,
                'aplicabilidad' => $campo['aplicabilidad'] ?? null,
            ];
        }

        return $resultado;
    }

    private function detectarUnidad(array $campo): ?string
    {
        $texto = mb_strtolower(($campo['ayuda'] ?? '').' '.($campo['etiqueta'] ?? ''));

        return str_contains($texto, 'centimetro') || str_contains($texto, 'centímetros') || str_contains($texto, 'centimetros')
            ? 'cm'
            : null;
    }
}
