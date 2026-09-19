<?php

namespace App\Service\WebPresupuesto\Admin;

final class LeadWebBudgetFlowPresenter
{
    /**
     * @return array<int, array{titulo: string, campos: array<int, array{label: string, valor: string, nota: ?string}>}>
     */
    public function configuracion(array $jsonSolicitudBudgetFlow): array
    {
        $valores = is_array($jsonSolicitudBudgetFlow['valores'] ?? null) ? $jsonSolicitudBudgetFlow['valores'] : [];
        $estimados = is_array($jsonSolicitudBudgetFlow['valores_estimados'] ?? null) ? $jsonSolicitudBudgetFlow['valores_estimados'] : [];
        $deducidos = is_array($jsonSolicitudBudgetFlow['valores_deducidos'] ?? null) ? $jsonSolicitudBudgetFlow['valores_deducidos'] : [];
        $aplazados = is_array($jsonSolicitudBudgetFlow['campos_aplazados'] ?? null) ? $jsonSolicitudBudgetFlow['campos_aplazados'] : [];
        $bloques = [];

        foreach ($valores as $componente => $campos) {
            if (!is_array($campos)) {
                continue;
            }

            $items = [];

            foreach ($campos as $campo => $valor) {
                if (is_array($valor)) {
                    foreach ($valor as $subCampo => $subValor) {
                        $items[] = $this->campo($componente, (string) $subCampo, $subValor, $estimados, $deducidos, $aplazados);
                    }

                    continue;
                }

                $items[] = $this->campo((string) $componente, (string) $campo, $valor, $estimados, $deducidos, $aplazados);
            }

            if ($items !== []) {
                $bloques[] = [
                    'titulo' => $this->labelComponente((string) $componente),
                    'campos' => $items,
                ];
            }
        }

        return $bloques;
    }

    /**
     * @return array{lineas: array<int, array<string, string>>, avisos: string[], total: ?string}
     */
    public function presupuesto(array $jsonPresupuesto): array
    {
        $lineas = [];

        foreach (($jsonPresupuesto['lineas'] ?? []) as $linea) {
            if (!is_array($linea)) {
                continue;
            }

            $lineas[] = [
                'descripcion' => $this->texto($linea['descripcion'] ?? ''),
                'cantidad' => $this->texto($linea['cantidad'] ?? ''),
                'unidad' => $this->texto($linea['unidad'] ?? ''),
                'precioUnitarioSinIva' => $this->importe($linea['precioUnitarioSinIva'] ?? null),
                'tipoIva' => $this->texto($linea['tipoIva'] ?? ''),
                'importeTotal' => $this->importe($linea['importeTotal'] ?? null),
            ];
        }

        $avisos = [];

        foreach (($jsonPresupuesto['avisos'] ?? []) as $aviso) {
            if (is_scalar($aviso) && trim((string) $aviso) !== '') {
                $avisos[] = trim((string) $aviso);
            } elseif (is_array($aviso)) {
                $texto = $aviso['mensaje'] ?? $aviso['texto'] ?? null;

                if (is_scalar($texto) && trim((string) $texto) !== '') {
                    $avisos[] = trim((string) $texto);
                }
            }
        }

        return [
            'lineas' => $lineas,
            'avisos' => $avisos,
            'total' => $this->importe($jsonPresupuesto['total'] ?? null),
        ];
    }

    private function campo(
        string $componente,
        string $campo,
        mixed $valor,
        array $estimados,
        array $deducidos,
        array $aplazados
    ): array {
        $id = $componente.'.'.$campo;
        $nota = null;

        if (in_array($id, $aplazados, true)) {
            $nota = 'Pendiente de confirmar';
        } elseif (isset($estimados[$componente][$campo]) || isset($estimados[$id])) {
            $nota = 'Estimado por el sistema';
        } elseif (isset($deducidos[$componente][$campo]) || isset($deducidos[$id])) {
            $nota = 'Deducido automáticamente';
        }

        return [
            'label' => $this->labelCampo($campo),
            'valor' => $this->valor($valor),
            'nota' => $nota,
        ];
    }

    private function labelComponente(string $componente): string
    {
        return match ($componente) {
            'selector_plato_ducha' => 'Plato',
            'web_mampara', 'selector_mampara' => 'Mampara',
            'griferia', 'selector_griferia' => 'Grifería',
            'web_revestimientos' => 'Revestimientos',
            'web_mano_obra_ducha' => 'Obra',
            default => ucfirst(str_replace(['web_', 'selector_', '_'], ['', '', ' '], $componente)),
        };
    }

    private function labelCampo(string $campo): string
    {
        return match ($campo) {
            'largo' => 'Largo',
            'ancho' => 'Ancho',
            'color' => 'Color',
            'tipo' => 'Tipo',
            'tipo_mampara' => 'Tipo de mampara',
            'ancho_frente' => 'Ancho frontal',
            'tipo_griferia' => 'Tipo de grifería',
            'cambiar_griferia' => 'Cambio de grifería',
            'azulejo' => 'Azulejo',
            'metros_azulejo' => 'Metros de azulejo',
            'tipo_trabajo' => 'Tipo de trabajo',
            default => ucfirst(str_replace('_', ' ', $campo)),
        };
    }

    private function valor(mixed $valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_numeric($valor)) {
            return (string) $valor;
        }

        if (!is_scalar($valor)) {
            return '';
        }

        $valor = (string) $valor;

        return match ($valor) {
            'frente' => 'Frontal',
            'fijo' => 'Fijo',
            'frente_con_lateral_fijo' => 'Frontal con lateral fijo',
            'sin_mampara' => 'Sin mampara',
            'manana' => 'Mañana',
            'tarde' => 'Tarde',
            'indiferente' => 'Me da igual',
            'banera_plato_zona_ducha' => 'Cambio de bañera por ducha',
            'banera_plato_cenefa' => 'Cambio de bañera con cenefa',
            'plato_por_plato' => 'Sustitución de plato',
            default => ucfirst(str_replace('_', ' ', $valor)),
        };
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    private function importe(mixed $valor): ?string
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        return number_format((float) $valor, 2, ',', '.').' €';
    }
}
