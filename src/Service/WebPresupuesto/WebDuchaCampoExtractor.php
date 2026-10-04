<?php

namespace App\Service\WebPresupuesto;

final class WebDuchaCampoExtractor
{
    public function __construct(
        private readonly WebPresupuestoCampoPath $campoPath = new WebPresupuestoCampoPath(),
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extraerCampos(array $configurador): array
    {
        if (($configurador['tipo'] ?? null) !== 'compuesto') {
            return $this->camposDeConfigurador($configurador['codigo'] ?? 'web_ducha', $configurador);
        }

        $campos = [];

        foreach ($configurador['componentes'] ?? [] as $indiceComponente => $componente) {
            $codigoComponente = $componente['codigo'] ?? null;
            $configuradorComponente = $componente['configurador'] ?? $componente;

            if (!$codigoComponente && is_array($configuradorComponente)) {
                $codigoComponente = $configuradorComponente['codigo'] ?? null;
            }

            if (!$codigoComponente || !is_array($configuradorComponente)) {
                continue;
            }

            foreach ($this->camposDeConfigurador($codigoComponente, $configuradorComponente, (int) ($componente['orden'] ?? 0), $indiceComponente) as $campo) {
                $campo['componente_obligatorio'] = (bool) ($componente['obligatorio'] ?? false);
                $campos[] = $campo;
            }
        }

        usort($campos, static fn(array $a, array $b): int => [
            $a['componente_orden'],
            $a['componente_indice'],
            $a['orden'],
            $a['campo_indice'],
        ] <=> [
            $b['componente_orden'],
            $b['componente_indice'],
            $b['orden'],
            $b['campo_indice'],
        ]);

        return $campos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function camposDeConfigurador(string $codigoComponente, array $configurador, ?int $ordenComponente = null, int $indiceComponente = 0): array
    {
        $resultado = [];

        foreach ($configurador['campos'] ?? [] as $indiceCampo => $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $path = is_string($campo['path'] ?? null) ? $campo['path'] : null;
            $partesPath = $path !== null ? $this->campoPath->separar($path) : null;
            $codigo = $campo['codigo'] ?? ($partesPath['campo'] ?? null);

            if (!$codigo) {
                continue;
            }

            $componente = $partesPath['componente'] ?? $codigoComponente;

            $resultado[] = [
                'id' => $path ?? $this->campoPath->construir($componente, $codigo),
                'path' => $path ?? $this->campoPath->construir($componente, $codigo),
                'componente' => $componente,
                'codigo' => $codigo,
                'etiqueta' => $campo['etiqueta'] ?? $codigo,
                'tipo' => $campo['tipo'] ?? ($campo['tipoCampo'] ?? 'texto'),
                'obligatorio' => (bool) ($campo['obligatorio'] ?? false),
                'orden' => (int) ($campo['orden'] ?? 0),
                'campo_indice' => $indiceCampo,
                'componente_orden' => $ordenComponente ?? (int) ($configurador['orden'] ?? 0),
                'componente_indice' => $indiceComponente,
                'ayuda' => $campo['ayuda'] ?? null,
                'unidad' => $campo['unidad'] ?? $this->detectarUnidad($campo),
                'opciones' => $campo['opciones'] ?? [],
                'valorDefecto' => $campo['valorDefecto'] ?? null,
                'valorEjemplo' => $campo['valorEjemplo'] ?? ($campo['ejemplo'] ?? null),
                'valorMinimo' => $campo['valorMinimo'] ?? null,
                'valorMaximo' => $campo['valorMaximo'] ?? null,
                'origenValor' => $campo['origenValor'] ?? null,
                'grupoEntrada' => $campo['grupoEntrada'] ?? null,
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
