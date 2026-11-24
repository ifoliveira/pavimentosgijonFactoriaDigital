<?php

namespace App\Service\Tesoreria;

/**
 * Estima las compras pendientes sin depender de pagos ni asignaciones a obras.
 * Todos los importes son bases sin IVA expresadas en céntimos.
 */
class BolsaGastosCalculator
{
    /**
     * @param array<array{
     *     id: string,
     *     tipo: 'presupuesto'|'factura'|'ajuste',
     *     fecha: \DateTimeImmutable,
     *     importeCentimos: int,
     *     concepto: string,
     *     fechaPrevista?: \DateTimeImmutable,
     *     proveedor?: string,
     *     documentoId?: int,
     *     facturaId?: int,
     *     proyectoId?: int
     * }> $eventos
     *
     * @return array{
     *     saldoCentimos: int,
     *     aportacionesCentimos: int,
     *     consumidoCentimos: int,
     *     ajustesCentimos: int,
     *     excesoFacturasCentimos: int,
     *     movimientos: array,
     *     pendientes: array
     * }
     */
    public function calcular(array $eventos): array
    {
        $prioridad = ['presupuesto' => 0, 'factura' => 1, 'ajuste' => 2];
        foreach ($eventos as $evento) {
            if (!isset($prioridad[$evento['tipo']])) {
                throw new \InvalidArgumentException('Tipo de movimiento de bolsa desconocido.');
            }
        }

        // Dentro de un día se aporta, se factura y después se corrige el saldo.
        // Un ajuste de compras aún pendientes no cubre excesos del mismo día.
        usort($eventos, static function (array $a, array $b) use ($prioridad): int {
            return strcmp($a['fecha']->format('Y-m-d'), $b['fecha']->format('Y-m-d'))
                ?: ($prioridad[$a['tipo']] <=> $prioridad[$b['tipo']])
                ?: strnatcmp($a['id'], $b['id']);
        });

        $resultado = [
            'saldoCentimos' => 0,
            'aportacionesCentimos' => 0,
            'consumidoCentimos' => 0,
            'ajustesCentimos' => 0,
            'excesoFacturasCentimos' => 0,
            'movimientos' => [],
            'pendientes' => [],
        ];
        $lotes = [];
        $vistos = [];
        $primerPendiente = 0;

        foreach ($eventos as $evento) {
            if (isset($vistos[$evento['id']])) {
                continue;
            }
            $vistos[$evento['id']] = true;

            $importe = $evento['importeCentimos'];
            $aplicado = 0;
            $exceso = 0;
            $incidencia = null;

            if ($evento['tipo'] === 'factura') {
                if ($importe < 0) {
                    // Sin correspondencia no sabemos qué reserva debe restaurar.
                    $incidencia = 'Abono pendiente de ajuste';
                } elseif ($importe > 0) {
                    $consumido = $this->consumir($lotes, $primerPendiente, $importe);
                    $aplicado = -$consumido;
                    $exceso = $importe - $consumido;
                    $resultado['consumidoCentimos'] += $consumido;
                    $resultado['excesoFacturasCentimos'] += $exceso;
                    if ($exceso > 0) {
                        $incidencia = 'Factura superior a la previsión disponible';
                    }
                }
            } else {
                if ($importe > 0) {
                    $lotes[] = array_merge($evento, ['restanteCentimos' => $importe]);
                    $aplicado = $importe;
                } elseif ($importe < 0) {
                    $retirado = $this->consumir($lotes, $primerPendiente, -$importe);
                    $aplicado = -$retirado;
                    $exceso = -$importe - $retirado;
                    if ($exceso > 0) {
                        $incidencia = 'Reducción superior a la previsión disponible';
                    }
                }

                $total = $evento['tipo'] === 'presupuesto' ? 'aportacionesCentimos' : 'ajustesCentimos';
                $resultado[$total] += $aplicado;
            }

            $resultado['saldoCentimos'] += $aplicado;
            $resultado['movimientos'][] = array_merge($evento, [
                'aplicadoCentimos' => $aplicado,
                'saldoCentimos' => $resultado['saldoCentimos'],
                'excesoCentimos' => $exceso,
                'incidencia' => $incidencia,
            ]);
        }

        $resultado['pendientes'] = array_values(array_filter(
            $lotes,
            static fn (array $lote): bool => $lote['restanteCentimos'] > 0,
        ));

        return $resultado;
    }

    /** Consume únicamente aportaciones existentes, por orden de entrada. */
    private function consumir(array &$lotes, int &$primerPendiente, int $importe): int
    {
        $restante = $importe;
        $numeroLotes = count($lotes);

        while ($restante > 0 && $primerPendiente < $numeroLotes) {
            $consumo = min($restante, $lotes[$primerPendiente]['restanteCentimos']);
            $lotes[$primerPendiente]['restanteCentimos'] -= $consumo;
            $restante -= $consumo;

            if ($lotes[$primerPendiente]['restanteCentimos'] === 0) {
                ++$primerPendiente;
            }
        }

        return $importe - $restante;
    }
}
