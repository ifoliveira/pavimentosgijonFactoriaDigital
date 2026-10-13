<?php

namespace App\Service\FichaVisita;

final class FichaVisitaConjuntoDuchaAdapter
{
    /**
     * @return array{valores: array, avisos: string[], pendientes: string[], observaciones: string[], sin_correspondencia: string[], componentes_excluidos: string[], procedencia: array, mapeo: array}
     */
    public function adaptar(array $datos, array $configurador): array
    {
        $tecnicos = is_array($datos['tecnicos'] ?? null) ? $datos['tecnicos'] : [];
        $casillas = is_array($datos['casillas'] ?? null) ? $datos['casillas'] : [];
        $avisos = $this->stringList($datos['contradicciones'] ?? []);
        $observaciones = $this->stringList($datos['observaciones'] ?? []);
        $pendientes = [];
        $sinCorrespondencia = [];
        $excluidos = [];
        $valores = [];
        $procedencia = [];
        $mapeo = [];

        $plato = is_array($tecnicos['plato'] ?? null) ? $tecnicos['plato'] : [];
        if (($largo = $this->intOrNull($plato['largo_cm'] ?? null)) !== null && $this->hasField($configurador, 'selector_plato_ducha', 'largo')) {
            $valores['selector_plato_ducha']['largo'] = $largo;
            $mapeo[] = $this->map('plato.largo_cm', 'selector_plato_ducha.largo', $largo);
        }
        if (($ancho = $this->intOrNull($plato['ancho_cm'] ?? null)) !== null && $this->hasField($configurador, 'selector_plato_ducha', 'ancho')) {
            $valores['selector_plato_ducha']['ancho'] = $ancho;
            $mapeo[] = $this->map('plato.ancho_cm', 'selector_plato_ducha.ancho', $ancho);
        }

        $color = $this->matchOption($configurador, 'selector_plato_ducha', 'color', (string) ($plato['color'] ?? $plato['modelo_color'] ?? ''));
        if ($color !== null) {
            $valores['selector_plato_ducha']['color'] = $color;
            $mapeo[] = $this->map('plato.color', 'selector_plato_ducha.color', $color);
        } elseif (!empty($plato['modelo_color'])) {
            $sinCorrespondencia[] = 'Modelo/color de plato sin opción equivalente: '.$plato['modelo_color'];
        }

        $alcance = $this->resolverAlcance($casillas['plato_retirada'] ?? [], $avisos, $pendientes);
        if ($alcance !== null && $this->hasOption($configurador, 'selector_mano_obra', 'tipo_trabajo', $alcance)) {
            $valores['selector_mano_obra']['tipo_trabajo'] = $alcance;
            $mapeo[] = $this->map('plato_retirada.alcance', 'selector_mano_obra.tipo_trabajo', $alcance);
        }

        $mampara = is_array($tecnicos['mampara'] ?? null) ? $tecnicos['mampara'] : [];
        $mamparaCasillas = is_array($casillas['mampara'] ?? null) ? $casillas['mampara'] : [];
        if (($mamparaCasillas['sin_mampara'] ?? false) === true) {
            $excluidos[] = 'selector_mampara';
            $mapeo[] = $this->map('mampara.sin_mampara', 'selector_mampara', 'excluido');
        } else {
            $tipoInstalacion = $this->resolverTipoMampara($mamparaCasillas, $avisos, $pendientes);
            if ($tipoInstalacion !== null) {
                $valores['selector_mampara']['tipo_instalacion'] = $tipoInstalacion;
                $mapeo[] = $this->map('mampara.tipo', 'selector_mampara.tipo_instalacion', $tipoInstalacion);
            }

            $this->resolverMedidasMampara(
                $valores,
                $procedencia,
                $mapeo,
                $pendientes,
                $tipoInstalacion,
                $largo,
                $ancho,
                $mampara
            );

            $apertura = $this->resolverApertura($mamparaCasillas, $avisos, $pendientes);
            if ($apertura !== null) {
                $valores['selector_mampara']['tipo_apertura'] = $apertura;
                $mapeo[] = $this->map('mampara.apertura', 'selector_mampara.tipo_apertura', $apertura);
            }

            foreach (['modelo', 'acabado'] as $campo) {
                $opcion = $this->matchOption($configurador, 'selector_mampara', $campo, (string) ($mampara[$campo] ?? ''));
                if ($opcion !== null) {
                    $valores['selector_mampara'][$campo] = $opcion;
                    $mapeo[] = $this->map('mampara.'.$campo, 'selector_mampara.'.$campo, $opcion);
                } elseif (!empty($mampara[$campo])) {
                    $sinCorrespondencia[] = ucfirst($campo).' de mampara sin opción equivalente: '.$mampara[$campo];
                }
            }
        }

        $griferia = is_array($tecnicos['griferia'] ?? null) ? $tecnicos['griferia'] : [];
        $griferiaCasillas = is_array($casillas['griferia'] ?? null) ? $casillas['griferia'] : [];
        $griferiaCasillasNormalizadas = $this->normalizarCasillasGriferia($griferiaCasillas, $griferia);
        $tipoGriferiaDetectado = $this->resolverTipoGriferia($griferiaCasillasNormalizadas, $avisos, $pendientes, false);

        if (($griferiaCasillas['conservar_grifo'] ?? false) === true && $tipoGriferiaDetectado === null) {
            $excluidos[] = 'griferia';
            $mapeo[] = $this->map('griferia.conservar_grifo', 'griferia', 'excluido');
        } else {
            if (($griferiaCasillas['conservar_grifo'] ?? false) === true) {
                $avisos[] = 'Grifería marcada como conservar grifo y con tipo seleccionado; se mantiene la grifería para revisión.';
            }

            $tipoGriferia = $this->resolverTipoGriferia(
                $griferiaCasillasNormalizadas,
                $avisos,
                $pendientes
            );
            if ($tipoGriferia !== null) {
                $valores['griferia']['uso'] = 'ducha';
                $valores['griferia']['tipo'] = $tipoGriferia;
                $mapeo[] = $this->map('griferia.tipo', 'griferia.tipo', $tipoGriferia);
            }
            if (!empty($griferia['modelo_acabado'])) {
                $valores['griferia']['descripcion'] = $griferia['modelo_acabado'];
                $mapeo[] = $this->map('griferia.modelo_acabado', 'griferia.descripcion', $griferia['modelo_acabado']);
            }
            if (($griferiaCasillas['sin_rociador'] ?? false) === true) {
                $sinCorrespondencia[] = 'Grifería marcada sin rociador; no existe campo equivalente en el configurador.';
            }
        }

        $revestimiento = is_array($tecnicos['revestimiento'] ?? null) ? $tecnicos['revestimiento'] : [];
        $revestimientoCasillas = is_array($casillas['revestimiento'] ?? null) ? $casillas['revestimiento'] : [];
        if ($this->hasField($configurador, 'revestimientos', 'azulejo')) {
            $valores['revestimientos']['azulejo'] = true;
        }
        $metrosAzulejo = $this->calcularMetrosAzulejo($revestimiento, $revestimientoCasillas, $avisos, $pendientes);
        if ($metrosAzulejo !== null && $this->hasField($configurador, 'revestimientos', 'metros_azulejo')) {
            $valores['revestimientos']['metros_azulejo'] = $metrosAzulejo;
            $mapeo[] = $this->map('revestimiento.medidas', 'revestimientos.metros_azulejo', $metrosAzulejo);
        }
        if (!empty($revestimiento['azulejo_extras'])) {
            $sinCorrespondencia[] = 'Azulejo/extras sin campo equivalente: '.$revestimiento['azulejo_extras'];
        }

        return [
            'valores' => $valores,
            'avisos' => array_values(array_unique($avisos)),
            'pendientes' => array_values(array_unique($pendientes)),
            'observaciones' => array_values(array_unique($observaciones)),
            'sin_correspondencia' => array_values(array_unique($sinCorrespondencia)),
            'componentes_excluidos' => array_values(array_unique($excluidos)),
            'procedencia' => $procedencia,
            'mapeo' => $mapeo,
        ];
    }

    public function reconciliarRevision(array $valores, array $adaptacionAnterior): array
    {
        $procedenciaAnterior = is_array($adaptacionAnterior['procedencia'] ?? null) ? $adaptacionAnterior['procedencia'] : [];
        $valoresAnteriores = is_array($adaptacionAnterior['valores'] ?? null) ? $adaptacionAnterior['valores'] : [];
        $procedencia = $procedenciaAnterior;
        $pendientes = [];
        $avisos = [];
        $mampara = is_array($valores['selector_mampara'] ?? null) ? $valores['selector_mampara'] : [];
        $tipo = (string) ($mampara['tipo_instalacion'] ?? '');
        $largo = $this->intOrNull($valores['selector_plato_ducha']['largo'] ?? null);
        $ancho = $this->intOrNull($valores['selector_plato_ducha']['ancho'] ?? null);

        if (in_array('selector_mampara', $adaptacionAnterior['componentes_excluidos'] ?? [], true)) {
            unset($valores['selector_mampara']);

            return [
                ...$adaptacionAnterior,
                'valores' => $valores,
                'pendientes' => [],
                'avisos' => [],
            ];
        }

        if ($tipo === 'frente') {
            unset($valores['selector_mampara']['ancho_lateral'], $procedencia['selector_mampara.ancho_lateral']);
            $this->reconciliarMedida(
                $valores,
                $procedencia,
                $pendientes,
                'ancho_frente',
                $largo,
                'selector_plato_ducha.largo',
                $valoresAnteriores
            );
        } elseif ($tipo === 'frente_con_lateral_fijo') {
            $this->reconciliarMedida(
                $valores,
                $procedencia,
                $pendientes,
                'ancho_frente',
                $largo,
                'selector_plato_ducha.largo',
                $valoresAnteriores
            );
            $this->reconciliarMedida(
                $valores,
                $procedencia,
                $pendientes,
                'ancho_lateral',
                $ancho,
                'selector_plato_ducha.ancho',
                $valoresAnteriores
            );
        } elseif ($tipo === 'fijo') {
            unset($valores['selector_mampara']['ancho_lateral'], $procedencia['selector_mampara.ancho_lateral']);
            $actual = $this->intOrNull($valores['selector_mampara']['ancho_frente'] ?? null);
            $anterior = $this->intOrNull($valoresAnteriores['selector_mampara']['ancho_frente'] ?? null);
            $anteriorDerivado = ($procedenciaAnterior['selector_mampara.ancho_frente']['tipo'] ?? null) === 'derivada';

            if ($actual === null || ($anteriorDerivado && $anterior !== null && $actual === $anterior)) {
                unset($valores['selector_mampara']['ancho_frente'], $procedencia['selector_mampara.ancho_frente']);
                $pendientes[] = 'selector_mampara.ancho_frente';
            } else {
                $valores['selector_mampara']['ancho_frente'] = $actual;
                $procedencia['selector_mampara.ancho_frente'] = [
                    'tipo' => 'usuario',
                    'origen' => 'revision',
                    'mensaje' => 'Informada expresamente por el usuario',
                ];
            }
        } else {
            $pendientes[] = 'selector_mampara.tipo_instalacion';
        }

        return [
            ...$adaptacionAnterior,
            'valores' => $valores,
            'procedencia' => $procedencia,
            'pendientes' => array_values(array_unique($pendientes)),
            'avisos' => $avisos,
        ];
    }

    public function completarGriferiaDesdeDatos(array $datos, array $adaptacion): array
    {
        $excluidos = is_array($adaptacion['componentes_excluidos'] ?? null) ? $adaptacion['componentes_excluidos'] : [];
        if (!in_array('griferia', $excluidos, true)) {
            return $adaptacion;
        }

        $tecnicos = is_array($datos['tecnicos'] ?? null) ? $datos['tecnicos'] : [];
        $casillas = is_array($datos['casillas'] ?? null) ? $datos['casillas'] : [];
        $griferia = is_array($tecnicos['griferia'] ?? null) ? $tecnicos['griferia'] : [];
        $griferiaCasillas = is_array($casillas['griferia'] ?? null) ? $casillas['griferia'] : [];
        $avisos = [];
        $pendientes = [];
        $tipo = $this->resolverTipoGriferia(
            $this->normalizarCasillasGriferia($griferiaCasillas, $griferia),
            $avisos,
            $pendientes,
            false
        );

        if ($tipo === null) {
            return $adaptacion;
        }

        $valores = is_array($adaptacion['valores'] ?? null) ? $adaptacion['valores'] : [];
        $valores['griferia']['uso'] = 'ducha';
        $valores['griferia']['tipo'] = $tipo;
        if (!empty($griferia['modelo_acabado'])) {
            $valores['griferia']['descripcion'] = $griferia['modelo_acabado'];
        }

        $adaptacion['valores'] = $valores;
        $adaptacion['componentes_excluidos'] = array_values(array_diff($excluidos, ['griferia']));
        $adaptacion['mapeo'] = array_values(array_filter(
            is_array($adaptacion['mapeo'] ?? null) ? $adaptacion['mapeo'] : [],
            static fn (array $item): bool => ($item['destino'] ?? null) !== 'griferia'
        ));
        $adaptacion['mapeo'][] = $this->map('griferia.tipo', 'griferia.tipo', $tipo);

        $avisosAdaptacion = is_array($adaptacion['avisos'] ?? null) ? $adaptacion['avisos'] : [];
        if (($griferiaCasillas['conservar_grifo'] ?? false) === true) {
            $avisosAdaptacion[] = 'Grifería marcada como conservar grifo y con tipo seleccionado; se mantiene la grifería para revisión.';
        }
        $adaptacion['avisos'] = array_values(array_unique($avisosAdaptacion));

        return $adaptacion;
    }

    private function resolverMedidasMampara(
        array &$valores,
        array &$procedencia,
        array &$mapeo,
        array &$pendientes,
        ?string $tipoInstalacion,
        ?int $largo,
        ?int $ancho,
        array $mampara
    ): void {
        if ($tipoInstalacion === 'frente') {
            if ($largo === null) {
                $pendientes[] = 'selector_plato_ducha.largo';
                return;
            }

            $valores['selector_mampara']['ancho_frente'] = $largo;
            $procedencia['selector_mampara.ancho_frente'] = [
                'tipo' => 'derivada',
                'origen' => 'selector_plato_ducha.largo',
                'mensaje' => 'Derivada del largo del plato',
            ];
            $mapeo[] = $this->map('plato.largo_cm', 'selector_mampara.ancho_frente', $largo);
            return;
        }

        if ($tipoInstalacion === 'frente_con_lateral_fijo') {
            if ($largo === null) {
                $pendientes[] = 'selector_plato_ducha.largo';
            } else {
                $valores['selector_mampara']['ancho_frente'] = $largo;
                $procedencia['selector_mampara.ancho_frente'] = [
                    'tipo' => 'derivada',
                    'origen' => 'selector_plato_ducha.largo',
                    'mensaje' => 'Derivada del largo del plato',
                ];
                $mapeo[] = $this->map('plato.largo_cm', 'selector_mampara.ancho_frente', $largo);
            }

            if ($ancho === null) {
                $pendientes[] = 'selector_plato_ducha.ancho';
            } else {
                $valores['selector_mampara']['ancho_lateral'] = $ancho;
                $procedencia['selector_mampara.ancho_lateral'] = [
                    'tipo' => 'derivada',
                    'origen' => 'selector_plato_ducha.ancho',
                    'mensaje' => 'Derivada del ancho del plato',
                ];
                $mapeo[] = $this->map('plato.ancho_cm', 'selector_mampara.ancho_lateral', $ancho);
            }

            return;
        }

        if ($tipoInstalacion === 'fijo') {
            $medidaFijo = $this->intOrNull($mampara['ancho_frente_cm'] ?? $mampara['medida_cm'] ?? $mampara['longitud_cm'] ?? null);
            if ($medidaFijo === null) {
                $pendientes[] = 'selector_mampara.ancho_frente';
                return;
            }

            $valores['selector_mampara']['ancho_frente'] = $medidaFijo;
            $procedencia['selector_mampara.ancho_frente'] = [
                'tipo' => 'usuario',
                'origen' => 'ficha',
                'mensaje' => 'Informada expresamente en la ficha',
            ];
            $mapeo[] = $this->map('mampara.medida_cm', 'selector_mampara.ancho_frente', $medidaFijo);
        }
    }

    private function reconciliarMedida(
        array &$valores,
        array &$procedencia,
        array &$pendientes,
        string $campo,
        ?int $valorDerivado,
        string $origen,
        array $valoresAnteriores
    ): void {
        $path = 'selector_mampara.'.$campo;
        $actual = $this->intOrNull($valores['selector_mampara'][$campo] ?? null);
        $anterior = $this->intOrNull($valoresAnteriores['selector_mampara'][$campo] ?? null);
        $procedenciaAnterior = $procedencia[$path]['tipo'] ?? null;

        if ($procedenciaAnterior === 'usuario' && $actual !== null) {
            $valores['selector_mampara'][$campo] = $actual;
            return;
        }

        if ($procedenciaAnterior === 'derivada' && $actual !== null && $anterior !== null && $actual !== $anterior) {
            $valores['selector_mampara'][$campo] = $actual;
            $procedencia[$path] = [
                'tipo' => 'usuario',
                'origen' => 'revision',
                'mensaje' => 'Informada expresamente por el usuario',
            ];
            return;
        }

        if ($valorDerivado === null) {
            unset($valores['selector_mampara'][$campo], $procedencia[$path]);
            $pendientes[] = $origen;
            return;
        }

        $valores['selector_mampara'][$campo] = $valorDerivado;
        $procedencia[$path] = [
            'tipo' => 'derivada',
            'origen' => $origen,
            'mensaje' => $origen === 'selector_plato_ducha.largo'
                ? 'Derivada del largo del plato'
                : 'Derivada del ancho del plato',
        ];
    }

    private function resolverAlcance(array $casillas, array &$avisos, array &$pendientes): ?string
    {
        $marcadas = array_keys(array_filter([
            'hasta_cenefa' => ($casillas['hasta_cenefa'] ?? false) === true,
            'hasta_techo' => ($casillas['hasta_techo'] ?? false) === true,
            'retirar_plato' => ($casillas['retirar_plato'] ?? false) === true,
        ]));

        if ($marcadas === []) {
            $pendientes[] = 'selector_mano_obra.tipo_trabajo';
            return null;
        }

        if (in_array('retirar_plato', $marcadas, true)) {
            return 'plato_por_plato';
        }

        if (count($marcadas) > 1) {
            $avisos[] = 'Varias casillas de alcance marcadas: '.implode(', ', $marcadas).'.';
            return null;
        }

        return $marcadas[0] === 'hasta_techo' ? 'banera_plato_zona_ducha' : 'banera_plato_cenefa';
    }

    private function resolverTipoMampara(array $casillas, array &$avisos, array &$pendientes): ?string
    {
        $marcadas = array_keys(array_filter([
            'entre_paredes' => ($casillas['entre_paredes'] ?? false) === true,
            'con_lateral' => ($casillas['con_lateral'] ?? false) === true,
            'cristal_fijo' => ($casillas['cristal_fijo'] ?? false) === true,
        ]));

        if ($marcadas === []) {
            $pendientes[] = 'selector_mampara.tipo_instalacion';
            return null;
        }

        if (count($marcadas) > 1) {
            $avisos[] = 'Varias casillas de tipo de mampara marcadas: '.implode(', ', $marcadas).'.';
            return null;
        }

        return match ($marcadas[0]) {
            'con_lateral' => 'frente_con_lateral_fijo',
            'cristal_fijo' => 'fijo',
            default => 'frente',
        };
    }

    private function resolverApertura(array $casillas, array &$avisos, array &$pendientes): ?string
    {
        if (($casillas['apertura_central'] ?? false) === true && ($casillas['corredera'] ?? false) === true) {
            return 'dos_fijos_dos_correderas';
        }

        $marcadas = array_keys(array_filter([
            'corredera' => ($casillas['corredera'] ?? false) === true,
            'abatible' => ($casillas['abatible'] ?? false) === true,
            'plegable' => ($casillas['plegable'] ?? false) === true,
            'apertura_central' => ($casillas['apertura_central'] ?? false) === true,
        ]));

        if ($marcadas === []) {
            $pendientes[] = 'selector_mampara.tipo_apertura';
            return null;
        }

        if (count($marcadas) > 1) {
            $avisos[] = 'Varias casillas de apertura de mampara marcadas: '.implode(', ', $marcadas).'.';
            return null;
        }

        return match ($marcadas[0]) {
            'abatible' => 'abatible',
            'plegable' => 'plegable',
            'apertura_central' => 'dos_fijos_dos_correderas',
            default => 'corredera',
        };
    }

    private function resolverTipoGriferia(array $casillas, array &$avisos, array &$pendientes, bool $registrarPendiente = true): ?string
    {
        $marcadas = array_keys(array_filter([
            'monomando' => $this->isTruthy($casillas['monomando'] ?? false),
            'termostatica' => $this->isTruthy($casillas['termostatica'] ?? $casillas['termostática'] ?? false),
            'empotrada' => $this->isTruthy($casillas['empotrada'] ?? $casillas['empotrado'] ?? false),
        ]));

        if ($marcadas === []) {
            if ($registrarPendiente) {
                $pendientes[] = 'griferia.tipo';
            }
            return null;
        }

        if (count($marcadas) > 1) {
            if ($registrarPendiente) {
                $avisos[] = 'Varias casillas de grifería marcadas: '.implode(', ', $marcadas).'.';
            }
            return null;
        }

        return match ($marcadas[0]) {
            'termostatica' => 'termostatica',
            'empotrada' => 'empotrado',
            default => 'monomando',
        };
    }

    private function normalizarCasillasGriferia(array $casillas, array $griferia): array
    {
        $normalizadas = $casillas;
        $texto = $this->normalize(implode(' ', array_filter(array_map(
            static fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null,
            [
                $griferia['tipo'] ?? null,
                $griferia['tipo_griferia'] ?? null,
                $griferia['modelo_acabado'] ?? null,
                $griferia['descripcion'] ?? null,
                $griferia['modelo'] ?? null,
                $griferia['acabado'] ?? null,
                $casillas['tipo'] ?? null,
                $casillas['tipo_griferia'] ?? null,
                $casillas['seleccion'] ?? null,
            ]
        ))));

        if (str_contains($texto, 'monomando')) {
            $normalizadas['monomando'] = true;
        }
        if (str_contains($texto, 'termostatica') || str_contains($texto, 'termostatico')) {
            $normalizadas['termostatica'] = true;
        }
        if (str_contains($texto, 'empotrada') || str_contains($texto, 'empotrado')) {
            $normalizadas['empotrada'] = true;
        }

        return $normalizadas;
    }

    private function calcularMetrosAzulejo(array $revestimiento, array $casillas, array &$avisos, array &$pendientes): ?int
    {
        $largo = $this->intOrNull($revestimiento['largo_cm'] ?? null);
        $ancho = $this->intOrNull($revestimiento['ancho_cm'] ?? null);
        $altura = $this->intOrNull($revestimiento['altura_cm'] ?? null);

        if ($largo === null || $ancho === null || $altura === null) {
            $pendientes[] = 'revestimientos.metros_azulejo';
            return null;
        }

        $dos = ($casillas['dos_paredes'] ?? false) === true;
        $tres = ($casillas['tres_paredes'] ?? false) === true;

        if ($dos && $tres) {
            $avisos[] = 'Marcadas dos y tres paredes en revestimiento.';
            return null;
        }

        if (!$dos && !$tres) {
            $pendientes[] = 'revestimiento.numero_paredes';
            return null;
        }

        $perimetroCm = $dos ? ($largo + $ancho) : ($largo + (2 * $ancho));

        return (int) ceil(($perimetroCm / 100) * ($altura / 100));
    }

    private function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/\d+/', $value, $m)) {
            return (int) $m[0];
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array($this->normalize($value), ['1', 'si', 'sí', 'true', 'x', 'marcado', 'seleccionado'], true);
    }

    private function hasField(array $configurador, string $component, string $field): bool
    {
        foreach ($configurador['componentes'] ?? [] as $componente) {
            if (($componente['codigo'] ?? null) !== $component) {
                continue;
            }

            foreach (($componente['configurador']['campos'] ?? $componente['campos'] ?? []) as $campo) {
                if (($campo['codigo'] ?? null) === $field) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasOption(array $configurador, string $component, string $field, string $option): bool
    {
        return $this->matchOption($configurador, $component, $field, $option) === $option;
    }

    private function matchOption(array $configurador, string $component, string $field, string $value): ?string
    {
        $value = $this->normalize($value);
        if ($value === '') {
            return null;
        }

        foreach ($configurador['componentes'] ?? [] as $componente) {
            if (($componente['codigo'] ?? null) !== $component) {
                continue;
            }
            foreach (($componente['configurador']['campos'] ?? $componente['campos'] ?? []) as $campo) {
                if (($campo['codigo'] ?? null) !== $field) {
                    continue;
                }
                foreach ($campo['opciones'] ?? [] as $opcion) {
                    $codigo = (string) ($opcion['codigo'] ?? '');
                    $etiqueta = (string) ($opcion['etiqueta'] ?? '');
                    if ($this->normalize($codigo) === $value || str_contains($this->normalize($etiqueta), $value)) {
                        return $codigo;
                    }
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    private function map(string $origen, string $destino, mixed $valor): array
    {
        return ['origen' => $origen, 'destino' => $destino, 'valor' => $valor];
    }
}
