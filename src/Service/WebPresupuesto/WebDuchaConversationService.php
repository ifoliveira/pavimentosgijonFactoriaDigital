<?php

namespace App\Service\WebPresupuesto;

use Psr\Log\LoggerInterface;

final class WebDuchaConversationService
{
    private const SITUACION_RESUELTO = 'RESUELTO';
    private const SITUACION_AMBIGUA = 'AMBIGUA';
    private const SITUACION_NO_ENTIENDE = 'NO_ENTIENDE';
    private const SITUACION_NO_SABE_ELEGIR = 'NO_SABE_ELEGIR';
    private const SITUACION_CLIENTE_NO_PUEDE_DECIDIR = 'CLIENTE_NO_PUEDE_DECIDIR';
    private const ESTADO_CAMPO_PENDIENTE = 'PENDIENTE';
    private const ESTADO_CAMPO_RESUELTO = 'RESUELTO';
    private const ESTADO_CAMPO_APLAZADO = 'APLAZADO_POR_CLIENTE';
    private const ESTADO_CONVERSACION_INCOMPLETA_APLAZADOS = 'INCOMPLETO_POR_DATOS_APLAZADOS';
    private const SELECCION_CLIENTE_NO_PUEDE_DECIDIR = '__cliente_no_puede_decidir__';
    private const LIMITE_INTENTOS_SIN_PROGRESO = 2;

    public function __construct(
        private readonly WebDuchaBudgetFlowProviderInterface $budgetFlowProvider,
        private readonly WebPresupuestoAiClientInterface $aiClient,
        private readonly WebDuchaCampoExtractor $campoExtractor,
        private readonly LoggerInterface $logger,
        private readonly array $valoresProvisionales = [],
    ) {
    }

    public function iniciar(): array
    {
        try {
            $configurador = $this->budgetFlowProvider->obtenerConfigurador();
        } catch (\Throwable $e) {
            $this->logger->error('BudgetFlow no disponible al iniciar presupuesto web ducha.', ['exception' => $e]);

            return [
                'ok' => false,
                'error' => 'No podemos iniciar el presupuestador ahora mismo.',
            ];
        }

        $estado = $this->estadoBase($configurador);

        return $this->continuar($estado);
    }

    public function responder(array $estado, ?string $respuesta, array $seleccion = []): array
    {
        $estado = $this->normalizarEstado($estado);
        $camposActuales = $estado['campos_pendientes_actuales'] ?? [];
        $valoresDetectados = [];
        $situacion = self::SITUACION_AMBIGUA;
        $traceId = WebDuchaDebugContext::getTraceId();

        $this->logger->debug('web_presupuesto_ducha.answer.estado_recibido', [
            'trace_id' => $traceId,
            'configurador_codigo' => $estado['configurador_codigo'] ?? null,
            'valores' => $estado['valores'],
            'campos_pendientes_actuales' => $camposActuales,
            'campos_actuales' => $this->resumenCamposPorIds($estado['campos'], $camposActuales),
        ]);

        $this->logger->debug('web_presupuesto_ducha.answer.respuesta_recibida', [
            'trace_id' => $traceId,
            'respuesta' => $respuesta,
            'seleccion' => $seleccion,
        ]);

        if ($seleccion !== []) {
            if ($this->seleccionClienteNoPuedeDecidir($seleccion)) {
                $situacion = self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR;
                $origenInterpretacion = 'seleccion_cliente_no_puede_decidir';
            } else {
                $valoresDetectados = $this->extraerSeleccionValida($camposActuales, $seleccion);
                $situacion = $valoresDetectados === [] ? self::SITUACION_AMBIGUA : self::SITUACION_RESUELTO;
                $origenInterpretacion = 'seleccion';
            }
        } elseif ($respuesta !== null && trim($respuesta) !== '') {
            $campos = array_values(array_filter(
                array_map(fn(string $id): ?array => $this->buscarCampo($estado['campos'], $id), $camposActuales),
                'is_array'
            ));
            [$valoresDetectados, $origenInterpretacion, $situacion] = $this->interpretarRespuestaConOrigen($campos, $respuesta, $estado['valores']);
        } else {
            $origenInterpretacion = 'sin_respuesta';
        }

        $this->logger->debug('web_presupuesto_ducha.answer.interpretacion', [
            'trace_id' => $traceId,
            'origen' => $origenInterpretacion,
            'situacion' => $situacion,
            'valores_detectados' => $valoresDetectados,
        ]);

        $valoresIncorporados = 0;
        $estado = $this->sincronizarEstadosCamposResueltos($estado);

        foreach ($valoresDetectados as $id => $valor) {
            $campo = $this->buscarCampo($estado['campos'], $id);

            if (!$campo || !$this->valorPermitido($campo, $valor)) {
                continue;
            }

            $estado['valores'][$campo['componente']][$campo['codigo']] = $this->normalizarValor($campo, $valor);
            $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_RESUELTO;
            $valoresIncorporados++;
        }

        $estado = $this->actualizarProgresoConversacional($estado, $camposActuales, $valoresIncorporados, $situacion);

        if ($situacion === self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR) {
            $resolucion = $this->resolverClienteNoPuedeDecidir($estado, $camposActuales);
            $estado = $resolucion['estado'];
            $estado['mensaje_prefijo'] = ($resolucion['aplazados'] ?? []) !== []
                ? 'No pasa nada. Lo confirmaremos más adelante.'
                : null;
        }

        $this->logger->debug('web_presupuesto_ducha.answer.estado_despues_incorporar', [
            'trace_id' => $traceId,
            'valores' => $estado['valores'],
            'intentos_sin_progreso' => $estado['intentos_sin_progreso'],
        ]);

        return $this->continuar($estado);
    }

    private function continuar(array $estado): array
    {
        $validacion = $this->validar($estado);
        $estado = $this->sincronizarEstadosCamposResueltos($estado);
        $pendientesConfiguracion = $this->camposPendientesParaConversacion($estado);
        $pendientes = $pendientesConfiguracion;
        $faltantesBudgetFlow = $this->faltantesDesdeErrores($validacion['errores'] ?? []);

        if (($validacion['valido'] ?? false) && $pendientes === []) {
            return $this->generar($estado, $validacion);
        }

        if ($pendientes === [] && !($validacion['valido'] ?? false)) {
            $pendientes = $this->pendientesDesdeErrores($estado, $validacion['errores'] ?? []);
        }

        if ($pendientes === [] && !($validacion['valido'] ?? false) && ($estado['campos_aplazados'] ?? []) !== []) {
            $generacionConEstimaciones = $this->intentarGenerarConValoresProvisionales($estado, $validacion);

            if ($generacionConEstimaciones !== null) {
                return $generacionConEstimaciones;
            }

            return $this->finalizarIncompletoPorAplazados($estado, $validacion);
        }

        $grupo = $this->agruparSiguiente($pendientes);
        $estado['campos_pendientes_actuales'] = array_map(static fn(array $campo): string => $campo['id'], $grupo);
        $estado['validacion'] = $validacion;
        $estado['finalizada'] = false;
        $grupoKey = $this->claveGrupo($estado['campos_pendientes_actuales']);
        $intentosSinProgreso = (int) ($estado['intentos_sin_progreso'][$grupoKey] ?? 0);

        if ($intentosSinProgreso > self::LIMITE_INTENTOS_SIN_PROGRESO) {
            $estado = $this->aplazarCampos($estado, $grupo);
            $estado['mensaje_prefijo'] = 'No pasa nada. Lo confirmaremos más adelante.';

            return $this->continuar($estado);
        }

        $preguntaDiagnostico = $this->generarPreguntaConDiagnostico($grupo, $estado['valores'], $estado['ultima_situacion'], $intentosSinProgreso);
        $pregunta = $preguntaDiagnostico['pregunta'];
        $prefijo = is_string($estado['mensaje_prefijo'] ?? null) ? $estado['mensaje_prefijo'] : null;
        unset($estado['mensaje_prefijo']);

        if ($prefijo !== null && $pregunta !== '') {
            $pregunta = $prefijo.' '.$pregunta;
        }

        $this->registrarDiagnosticoInteraccion(
            $estado,
            $validacion,
            $pendientesConfiguracion,
            $pendientes,
            $grupo,
            $faltantesBudgetFlow,
            $preguntaDiagnostico
        );

        $this->logger->debug('web_presupuesto_ducha.answer.pendientes_despues_validar', [
            'trace_id' => WebDuchaDebugContext::getTraceId(),
            'validacion' => $validacion,
            'pendientes_ids' => array_map(static fn(array $campo): string => $campo['id'], $pendientes),
            'grupo_siguiente' => $estado['campos_pendientes_actuales'],
            'grupo_siguiente_campos' => $grupo,
            'pregunta' => $pregunta,
        ]);

        return [
            'ok' => true,
            'finalizada' => false,
            'estado' => $estado,
            'pregunta' => $pregunta,
            'campos' => $grupo,
            'opciones' => $this->opcionesParaGrupo($grupo, $intentosSinProgreso >= self::LIMITE_INTENTOS_SIN_PROGRESO),
            'errores' => $validacion['errores'] ?? [],
            'situacion' => $estado['ultima_situacion'],
            'intentos_sin_progreso' => $intentosSinProgreso,
            'estado_conversacion' => 'EN_CURSO',
            'campos_aplazados' => $estado['campos_aplazados'] ?? [],
        ];
    }

    private function generar(array $estado, array $validacion): array
    {
        try {
            $resultado = $this->budgetFlowProvider->generar($estado['configurador'], $estado['valores']);
        } catch (\Throwable $e) {
            $this->logger->error('BudgetFlow no disponible al generar presupuesto web ducha.', ['exception' => $e]);

            return [
                'ok' => false,
                'error' => 'No hemos podido generar el presupuesto ahora mismo.',
                'estado' => $estado,
            ];
        }

        $estado['finalizada'] = true;
        $estado['validacion'] = $validacion;

        return [
            'ok' => true,
            'finalizada' => true,
            'estado' => $estado,
            'resultado' => $this->normalizarResultado($resultado),
            'contiene_estimaciones' => false,
            'valores_estimados' => [],
        ];
    }

    private function generarConValoresCalculo(array $estado, array $validacion, array $valoresCalculo, array $valoresEstimados): array
    {
        try {
            $resultado = $this->budgetFlowProvider->generar($estado['configurador'], $valoresCalculo);
        } catch (\Throwable $e) {
            $this->logger->error('BudgetFlow no disponible al generar presupuesto web ducha con valores provisionales.', ['exception' => $e]);

            return [
                'ok' => false,
                'error' => 'No hemos podido generar el presupuesto ahora mismo.',
                'estado' => $estado,
            ];
        }

        $estado['finalizada'] = true;
        $estado['validacion'] = $validacion;
        $estado['valores_estimados'] = $valoresEstimados;
        $estado['valores_calculo'] = $valoresCalculo;

        $resultadoNormalizado = $this->normalizarResultado($resultado);
        $resultadoNormalizado['contiene_estimaciones'] = true;
        $resultadoNormalizado['valores_estimados'] = $valoresEstimados;

        return [
            'ok' => true,
            'finalizada' => true,
            'estado' => $estado,
            'resultado' => $resultadoNormalizado,
            'contiene_estimaciones' => true,
            'valores_estimados' => $valoresEstimados,
            'mensaje' => 'Hemos utilizado medidas orientativas para los datos que no tenías disponibles. Las confirmaremos antes de realizar el pedido.',
        ];
    }

    private function estadoBase(array $configurador): array
    {
        return [
            'configurador_codigo' => 'web_ducha',
            'configurador' => $configurador,
            'campos' => $this->campoExtractor->extraerCampos($configurador),
            'valores' => [],
            'campos_pendientes_actuales' => [],
            'validacion' => null,
            'finalizada' => false,
            'intentos_sin_progreso' => [],
            'ultima_situacion' => null,
            'decisiones_pendientes' => [],
            'campos_omitidos' => [],
            'campos_aplazados' => [],
            'estado_campos' => [],
            'valores_estimados' => [],
            'estado_conversacion' => 'EN_CURSO',
        ];
    }

    private function normalizarEstado(array $estado): array
    {
        $estado['valores'] = is_array($estado['valores'] ?? null) ? $estado['valores'] : [];
        $estado['campos'] = is_array($estado['campos'] ?? null) ? $estado['campos'] : [];
        $estado['configurador'] = is_array($estado['configurador'] ?? null) ? $estado['configurador'] : [];
        $estado['intentos_sin_progreso'] = is_array($estado['intentos_sin_progreso'] ?? null) ? $estado['intentos_sin_progreso'] : [];
        $estado['decisiones_pendientes'] = is_array($estado['decisiones_pendientes'] ?? null) ? $estado['decisiones_pendientes'] : [];
        $estado['campos_omitidos'] = is_array($estado['campos_omitidos'] ?? null) ? $estado['campos_omitidos'] : [];
        $estado['campos_aplazados'] = is_array($estado['campos_aplazados'] ?? null) ? array_values(array_unique(array_map('strval', $estado['campos_aplazados']))) : [];
        $estado['estado_campos'] = is_array($estado['estado_campos'] ?? null) ? $estado['estado_campos'] : [];
        $estado['valores_estimados'] = is_array($estado['valores_estimados'] ?? null) ? $estado['valores_estimados'] : [];
        $estado['estado_conversacion'] = is_string($estado['estado_conversacion'] ?? null) ? $estado['estado_conversacion'] : 'EN_CURSO';
        $estado['ultima_situacion'] = is_string($estado['ultima_situacion'] ?? null) ? $estado['ultima_situacion'] : null;

        return $estado;
    }

    private function validar(array $estado): array
    {
        try {
            return $this->budgetFlowProvider->validar($estado['configurador'], $estado['valores']);
        } catch (\Throwable $e) {
            $this->logger->error('BudgetFlow no disponible al validar presupuesto web ducha.', ['exception' => $e]);

            return [
                'valido' => false,
                'errores' => [
                    [
                        'codigo' => 'budgetflow_no_disponible',
                        'mensaje' => 'No podemos validar el presupuesto ahora mismo.',
                    ],
                ],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function camposPendientesParaConversacion(array $estado): array
    {
        return array_values(array_filter(
            $estado['campos'],
            function (array $campo) use ($estado): bool {
                if ($this->estadoCampo($estado, $campo) === self::ESTADO_CAMPO_APLAZADO) {
                    return false;
                }

                if (in_array($campo['id'], $estado['campos_omitidos'] ?? [], true)) {
                    return false;
                }

                if (!$this->debePreguntarse($campo)) {
                    return false;
                }

                return !array_key_exists($campo['codigo'], $estado['valores'][$campo['componente']] ?? []);
            }
        ));
    }

    private function sincronizarEstadosCamposResueltos(array $estado): array
    {
        foreach ($estado['campos'] as $campo) {
            if (array_key_exists($campo['codigo'], $estado['valores'][$campo['componente']] ?? [])) {
                $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_RESUELTO;
                $estado['campos_aplazados'] = array_values(array_diff($estado['campos_aplazados'] ?? [], [$campo['id']]));
                continue;
            }

            if (!isset($estado['estado_campos'][$campo['id']])) {
                $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_PENDIENTE;
            }
        }

        return $estado;
    }

    private function estadoCampo(array $estado, array $campo): string
    {
        if (array_key_exists($campo['codigo'], $estado['valores'][$campo['componente']] ?? [])) {
            return self::ESTADO_CAMPO_RESUELTO;
        }

        if (in_array($campo['id'], $estado['campos_aplazados'] ?? [], true)) {
            return self::ESTADO_CAMPO_APLAZADO;
        }

        return is_string($estado['estado_campos'][$campo['id']] ?? null)
            ? $estado['estado_campos'][$campo['id']]
            : self::ESTADO_CAMPO_PENDIENTE;
    }

    private function debePreguntarse(array $campo): bool
    {
        if (($campo['valorDefecto'] ?? null) !== null) {
            return false;
        }

        return (bool) ($campo['obligatorio'] ?? false);
    }

    /**
     * @param array<int, array<string, mixed>> $pendientes
     *
     * @return array<int, array<string, mixed>>
     */
    private function agruparSiguiente(array $pendientes): array
    {
        if ($pendientes === []) {
            return [];
        }

        $primero = $pendientes[0];

        $relacionados = array_values(array_filter(
            $pendientes,
            static fn(array $campo): bool => $campo['componente'] === $primero['componente']
                && $campo['tipo'] === $primero['tipo']
                && ($campo['unidad'] ?? null) === ($primero['unidad'] ?? null)
        ));

        if ($this->esGrupoMedidas($relacionados)) {
            usort($relacionados, static function (array $a, array $b): int {
                $orden = ['largo' => 0, 'ancho' => 1];

                return ($orden[$a['codigo']] ?? 99) <=> ($orden[$b['codigo']] ?? 99);
            });
        }

        return array_slice($relacionados, 0, 2);
    }

    private function generarPregunta(array $grupo, array $valores): string
    {
        return $this->generarPreguntaConDiagnostico($grupo, $valores, null, 0)['pregunta'];
    }

    /**
     * @return array{pregunta: string, fuente: string, ia_respuesta: ?string, fallback_motivo: ?string}
     */
    private function generarPreguntaConDiagnostico(array $grupo, array $valores, ?string $situacion, int $intentosSinProgreso): array
    {
        if ($grupo === []) {
            return [
                'pregunta' => 'Necesito un dato más para continuar.',
                'fuente' => 'fallback',
                'ia_respuesta' => null,
                'fallback_motivo' => 'grupo_siguiente_vacio',
            ];
        }

        if ($intentosSinProgreso >= self::LIMITE_INTENTOS_SIN_PROGRESO) {
            return [
                'pregunta' => $this->preguntaConSalidaClienteNoSabe($grupo),
                'fuente' => 'salida_cliente_no_sabe',
                'ia_respuesta' => null,
                'fallback_motivo' => null,
            ];
        }

        if ($intentosSinProgreso === 1 && in_array($situacion, [self::SITUACION_NO_ENTIENDE, self::SITUACION_NO_SABE_ELEGIR], true)) {
            return [
                'pregunta' => $this->explicacionDesdeMetadatos($grupo),
                'fuente' => 'explicacion_metadatos',
                'ia_respuesta' => null,
                'fallback_motivo' => null,
            ];
        }

        if ($this->esGrupoMedidas($grupo)) {
            return [
                'pregunta' => '¿Qué medidas aproximadas tiene la bañera? Puedes indicarme largo x ancho, por ejemplo 160 x 70 cm.',
                'fuente' => 'determinista_medidas',
                'ia_respuesta' => null,
                'fallback_motivo' => null,
            ];
        }

        try {
            $pregunta = $this->aiClient->generarPregunta($this->camposParaIa($grupo), $valores);

            if ($pregunta !== '') {
                return [
                    'pregunta' => $pregunta,
                    'fuente' => 'ia',
                    'ia_respuesta' => $pregunta,
                    'fallback_motivo' => null,
                ];
            }

            $fallbackMotivo = 'ia_respuesta_vacia';
        } catch (\Throwable $e) {
            $this->logger->error('IA no disponible al generar pregunta web ducha.', ['exception' => $e]);
            $fallbackMotivo = 'ia_error: '.$e->getMessage();
        }

        return [
            'pregunta' => $this->preguntaFallback($grupo),
            'fuente' => 'fallback',
            'ia_respuesta' => $pregunta ?? null,
            'fallback_motivo' => $fallbackMotivo,
        ];
    }

    private function interpretarRespuesta(array $campos, string $respuesta, array $valores): array
    {
        return $this->interpretarRespuestaConOrigen($campos, $respuesta, $valores)[0];
    }

    /**
     * @param array<int, array<string, mixed>> $campos
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function interpretarRespuestaConOrigen(array $campos, string $respuesta, array $valores): array
    {
        $situacionDeterminista = $this->detectarSituacionConversacional($respuesta);

        if ($situacionDeterminista !== null) {
            return [[], 'determinista_situacion', $situacionDeterminista];
        }

        $determinista = $this->interpretarDeterminista($campos, $respuesta);

        if ($determinista !== []) {
            return [$determinista, 'determinista', self::SITUACION_RESUELTO];
        }

        if ($this->esGrupoMedidas($campos)) {
            $valoresMedidas = $this->interpretarMedidas($campos, $respuesta);

            return [$valoresMedidas, 'determinista_medidas', $valoresMedidas === [] ? self::SITUACION_AMBIGUA : self::SITUACION_RESUELTO];
        }

        try {
            $interpretacion = $this->aiClient->interpretarRespuesta($this->camposParaIa($campos), $respuesta, $valores);
        } catch (\Throwable $e) {
            $this->logger->error('IA no disponible al interpretar respuesta web ducha.', ['exception' => $e]);

            return [[], 'ia_error', self::SITUACION_AMBIGUA];
        }

        $situacion = $this->normalizarSituacion($interpretacion['situacion'] ?? null);

        if (($interpretacion['requiere_aclaracion'] ?? false) === true) {
            return [[], 'ia_aclaracion', $situacion ?? self::SITUACION_AMBIGUA];
        }

        $valoresDetectados = is_array($interpretacion['valores_detectados'] ?? null)
            ? $this->normalizarClavesDetectadas($campos, $interpretacion['valores_detectados'])
            : [];

        return [$valoresDetectados, 'ia', $situacion ?? ($valoresDetectados === [] ? self::SITUACION_AMBIGUA : self::SITUACION_RESUELTO)];
    }

    /**
     * @param array<int, array<string, mixed>> $campos
     *
     * @return array<string, mixed>
     */
    private function interpretarDeterminista(array $campos, string $respuesta): array
    {
        if (count($campos) !== 1) {
            return [];
        }

        $campo = $campos[0];

        if (!$this->esCampoNumerico($campo)) {
            return [];
        }

        $numero = $this->extraerNumeroUnico($respuesta);

        if ($numero === null) {
            return [];
        }

        return [
            $campo['id'] => $this->normalizarNumeroPorUnidad($numero, $respuesta, $campo),
        ];
    }

    private function detectarSituacionConversacional(string $respuesta): ?string
    {
        $normalizada = $this->normalizarTexto($respuesta);

        $noEntiende = [
            'no entiendo',
            'no lo entiendo',
            'no comprendo',
            'que significa',
            'qué significa',
            'explicame',
            'explícame',
            'ponme un ejemplo',
            'dame un ejemplo',
            'algun ejemplo',
            'algún ejemplo',
            'necesito un ejemplo',
        ];

        foreach ($noEntiende as $patron) {
            if (str_contains($normalizada, $this->normalizarTexto($patron))) {
                return self::SITUACION_NO_ENTIENDE;
            }
        }

        $noSabeElegir = [
            'no se que elegir',
            'no sé qué elegir',
            'no se cual elegir',
            'no sé cuál elegir',
            'no se elegir',
            'no sé elegir',
            'no se',
            'no sé',
            'me da igual',
            'prefiero que me recomendeis',
            'prefiero que me recomendéis',
            'recomendadme',
            'recomiendame',
            'recomiéndame',
        ];

        foreach ($noSabeElegir as $patron) {
            if ($normalizada === $this->normalizarTexto($patron) || str_contains($normalizada, $this->normalizarTexto($patron))) {
                return self::SITUACION_NO_SABE_ELEGIR;
            }
        }

        return null;
    }

    private function normalizarSituacion(mixed $situacion): ?string
    {
        if (!is_string($situacion)) {
            return null;
        }

        $situacion = strtoupper(trim($situacion));

        return in_array($situacion, [
            self::SITUACION_RESUELTO,
            self::SITUACION_AMBIGUA,
            self::SITUACION_NO_ENTIENDE,
            self::SITUACION_NO_SABE_ELEGIR,
            self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR,
        ], true) ? $situacion : null;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    private function esCampoNumerico(array $campo): bool
    {
        return in_array($campo['tipo'] ?? null, ['entero', 'decimal'], true);
    }

    private function extraerNumeroUnico(string $respuesta): ?float
    {
        $normalizada = mb_strtolower(trim($respuesta));
        $normalizada = str_replace(',', '.', $normalizada);

        preg_match_all('/\d+(?:\.\d+)?/', $normalizada, $matches);
        $numeros = $matches[0] ?? [];

        if (count($numeros) !== 1) {
            return null;
        }

        return (float) $numeros[0];
    }

    private function normalizarNumeroPorUnidad(float $numero, string $respuesta, array $campo): float|int
    {
        $normalizada = mb_strtolower($respuesta);
        $unidad = $campo['unidad'] ?? null;
        $expresadoEnMetros = preg_match('/(^|[^a-záéíóúñ])m\b/u', $normalizada) === 1;

        if ($unidad === 'cm' && $expresadoEnMetros) {
            $numero *= 100;
        } elseif ($unidad === 'cm' && $numero > 0 && $numero < 10) {
            $numero *= 100;
        }

        return ($campo['tipo'] ?? null) === 'entero'
            ? (int) round($numero)
            : (float) round($numero, 2);
    }

    /**
     * @param array<int, array<string, mixed>> $campos
     * @param array<string, mixed> $valores
     *
     * @return array<string, mixed>
     */
    private function normalizarClavesDetectadas(array $campos, array $valores): array
    {
        if (count($campos) !== 1) {
            return $valores;
        }

        $campo = $campos[0];

        if (array_key_exists($campo['id'], $valores)) {
            return $valores;
        }

        if (array_key_exists($campo['codigo'], $valores)) {
            return [
                $campo['id'] => $valores[$campo['codigo']],
            ];
        }

        return $valores;
    }

    /**
     * @param array<int, array<string, mixed>> $campos
     *
     * @return array<string, mixed>
     */
    private function interpretarMedidas(array $campos, string $respuesta): array
    {
        $normalizada = mb_strtolower($respuesta);
        $normalizada = str_replace(',', '.', $normalizada);

        preg_match_all('/\d+(?:\.\d+)?/', $normalizada, $matches);
        $numeros = array_map('floatval', $matches[0] ?? []);

        if (count($numeros) < 2) {
            return [];
        }

        $valores = [];

        foreach (array_slice($campos, 0, 2) as $index => $campo) {
            $numero = $numeros[$index];
            $valores[$campo['id']] = $numero < 10 ? (int) round($numero * 100) : (int) round($numero);
        }

        return $valores;
    }

    private function extraerSeleccionValida(array $idsCampos, array $seleccion): array
    {
        $valores = [];

        foreach ($idsCampos as $id) {
            if (array_key_exists($id, $seleccion)) {
                $valores[$id] = $seleccion[$id];
            }
        }

        return $valores;
    }

    private function seleccionClienteNoPuedeDecidir(array $seleccion): bool
    {
        foreach ($seleccion as $valor) {
            if ($valor === self::SELECCION_CLIENTE_NO_PUEDE_DECIDIR) {
                return true;
            }
        }

        return false;
    }

    private function actualizarProgresoConversacional(array $estado, array $idsCampos, int $valoresIncorporados, string $situacion): array
    {
        if ($idsCampos === []) {
            return $estado;
        }

        $grupoKey = $this->claveGrupo($idsCampos);

        if ($valoresIncorporados > 0) {
            $estado['intentos_sin_progreso'][$grupoKey] = 0;
            $estado['ultima_situacion'] = self::SITUACION_RESUELTO;

            return $estado;
        }

        $estado['intentos_sin_progreso'][$grupoKey] = ((int) ($estado['intentos_sin_progreso'][$grupoKey] ?? 0)) + 1;
        $estado['ultima_situacion'] = $situacion;

        return $estado;
    }

    /**
     * @return array{estado: array<string, mixed>, aplazados: array<int, array<string, mixed>>}
     */
    private function resolverClienteNoPuedeDecidir(array $estado, array $idsCampos): array
    {
        $aplazados = [];

        foreach ($idsCampos as $id) {
            $campo = $this->buscarCampo($estado['campos'], (string) $id);

            if (!$campo) {
                continue;
            }

            if (array_key_exists('valorDefecto', $campo) && ($campo['valorDefecto'] ?? null) !== null) {
                $estado['valores'][$campo['componente']][$campo['codigo']] = $this->normalizarValor($campo, $campo['valorDefecto']);
                continue;
            }

            if (!($campo['obligatorio'] ?? false)) {
                $estado['campos_omitidos'][] = $campo['id'];
                $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_RESUELTO;
                continue;
            }

            $estado = $this->aplazarCampos($estado, [$campo]);
            $aplazados[] = $campo;
        }

        return [
            'estado' => $estado,
            'aplazados' => $aplazados,
        ];
    }

    private function aplazarCampos(array $estado, array $campos): array
    {
        foreach ($campos as $campo) {
            if (!is_array($campo) || !isset($campo['id'])) {
                continue;
            }

            if (array_key_exists($campo['codigo'], $estado['valores'][$campo['componente']] ?? [])) {
                $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_RESUELTO;
                continue;
            }

            if (array_key_exists('valorDefecto', $campo) && ($campo['valorDefecto'] ?? null) !== null) {
                $estado['valores'][$campo['componente']][$campo['codigo']] = $this->normalizarValor($campo, $campo['valorDefecto']);
                $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_RESUELTO;
                continue;
            }

            if (!($campo['obligatorio'] ?? false)) {
                $estado['campos_omitidos'][] = $campo['id'];
                $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_RESUELTO;
                continue;
            }

            $estado['estado_campos'][$campo['id']] = self::ESTADO_CAMPO_APLAZADO;
            $estado['campos_aplazados'][] = $campo['id'];
        }

        $estado['campos_aplazados'] = array_values(array_unique($estado['campos_aplazados'] ?? []));
        $estado['campos_omitidos'] = array_values(array_unique($estado['campos_omitidos'] ?? []));

        return $estado;
    }

    private function finalizarIncompletoPorAplazados(array $estado, array $validacion): array
    {
        $camposAplazados = array_values(array_filter(
            array_map(fn(string $id): ?array => $this->buscarCampo($estado['campos'], $id), $estado['campos_aplazados'] ?? []),
            'is_array'
        ));

        $pendientes = array_map(static fn(array $campo): array => [
            'id' => $campo['id'] ?? null,
            'componente' => $campo['componente'] ?? null,
            'codigo' => $campo['codigo'] ?? null,
            'etiqueta' => $campo['etiqueta'] ?? ($campo['codigo'] ?? null),
            'motivo' => 'cliente_no_sabe',
        ], $camposAplazados);

        $estado['finalizada'] = true;
        $estado['decisiones_pendientes'] = $pendientes;
        $estado['ultima_situacion'] = self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR;
        $estado['campos_pendientes_actuales'] = [];
        $estado['validacion'] = $validacion;
        $estado['estado_conversacion'] = self::ESTADO_CONVERSACION_INCOMPLETA_APLAZADOS;

        return [
            'ok' => true,
            'finalizada' => true,
            'pendiente' => false,
            'incompleto' => true,
            'estado' => $estado,
            'pregunta' => 'Ya tengo el resto de información. Para calcular el presupuesto necesito confirmar algunos datos que ahora no tienes disponibles.',
            'campos' => [],
            'opciones' => [],
            'situacion' => self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR,
            'estado_conversacion' => self::ESTADO_CONVERSACION_INCOMPLETA_APLAZADOS,
            'motivo' => 'datos_aplazados',
            'campos_aplazados' => $estado['campos_aplazados'] ?? [],
            'decisiones_pendientes' => $pendientes,
            'errores' => $validacion['errores'] ?? [],
        ];
    }

    private function intentarGenerarConValoresProvisionales(array $estado, array $validacion): ?array
    {
        $faltantesBudgetFlow = $this->faltantesDesdeErrores($validacion['errores'] ?? []);

        if ($faltantesBudgetFlow === []) {
            return null;
        }

        [$valoresCalculo, $valoresEstimados, $puedeEstimar] = $this->construirValoresCalculoConEstimaciones($estado, $faltantesBudgetFlow);

        if (!$puedeEstimar || $valoresEstimados === []) {
            return null;
        }

        $validacionCalculo = $this->validar([
            'configurador' => $estado['configurador'],
            'valores' => $valoresCalculo,
        ]);

        if (!($validacionCalculo['valido'] ?? false)) {
            return null;
        }

        return $this->generarConValoresCalculo($estado, $validacionCalculo, $valoresCalculo, $valoresEstimados);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: bool}
     */
    private function construirValoresCalculoConEstimaciones(array $estado, array $faltantesBudgetFlow): array
    {
        $valoresCalculo = $estado['valores'];
        $valoresEstimados = [];

        foreach ($faltantesBudgetFlow as $faltante) {
            $id = $faltante['id'] ?? null;

            if (!is_string($id) || !in_array($id, $estado['campos_aplazados'] ?? [], true)) {
                return [$valoresCalculo, $valoresEstimados, false];
            }

            $campo = $this->buscarCampo($estado['campos'], $id);

            if (!$campo) {
                return [$valoresCalculo, $valoresEstimados, false];
            }

            $valorProvisional = $this->valorProvisionalParaCampo($estado, $campo);

            if ($valorProvisional === null) {
                return [$valoresCalculo, $valoresEstimados, false];
            }

            $valorNormalizado = $this->normalizarValor($campo, $valorProvisional);
            $valoresCalculo[$campo['componente']][$campo['codigo']] = $valorNormalizado;
            $valoresEstimados[] = [
                'campo' => $campo['id'],
                'componente' => $campo['componente'],
                'codigo' => $campo['codigo'],
                'valor' => $valorNormalizado,
                'origen' => 'ESTIMACION',
            ];
        }

        usort($valoresEstimados, static fn(array $a, array $b): int => ((string) ($a['campo'] ?? '')) <=> ((string) ($b['campo'] ?? '')));

        return [$valoresCalculo, $valoresEstimados, true];
    }

    private function valorProvisionalParaCampo(array $estado, array $campo): mixed
    {
        $configuradorCodigo = $estado['configurador_codigo'] ?? ($estado['configurador']['codigo'] ?? null);
        $id = $campo['id'] ?? null;

        if (!is_string($configuradorCodigo) || !is_string($id)) {
            return null;
        }

        $porConfigurador = $this->valoresProvisionales[$configuradorCodigo] ?? [];

        if (!is_array($porConfigurador) || !array_key_exists($id, $porConfigurador)) {
            return null;
        }

        return $porConfigurador[$id];
    }

    private function claveGrupo(array $idsCampos): string
    {
        $ids = array_values(array_map('strval', $idsCampos));
        sort($ids);

        return implode('|', $ids);
    }

    private function buscarCampo(array $campos, string $id): ?array
    {
        foreach ($campos as $campo) {
            if (($campo['id'] ?? null) === $id) {
                return $campo;
            }
        }

        return null;
    }

    private function resumenCamposPorIds(array $campos, array $ids): array
    {
        $resumen = [];

        foreach ($ids as $id) {
            $campo = $this->buscarCampo($campos, (string) $id);

            if (!$campo) {
                $resumen[] = [
                    'id' => $id,
                    'encontrado' => false,
                ];
                continue;
            }

            $resumen[] = [
                'id' => $campo['id'] ?? null,
                'componente' => $campo['componente'] ?? null,
                'codigo' => $campo['codigo'] ?? null,
                'tipo' => $campo['tipo'] ?? null,
                'obligatorio' => $campo['obligatorio'] ?? null,
                'unidad' => $campo['unidad'] ?? null,
                'opciones' => $campo['opciones'] ?? [],
                'valorDefecto' => $campo['valorDefecto'] ?? null,
            ];
        }

        return $resumen;
    }

    private function registrarDiagnosticoInteraccion(
        array $estado,
        array $validacion,
        array $pendientesConfiguracion,
        array $pendientesFinales,
        array $grupo,
        array $faltantesBudgetFlow,
        array $preguntaDiagnostico
    ): void {
        $inconsistencias = $this->inconsistenciasBudgetFlow($estado['campos'], $faltantesBudgetFlow);

        if ($inconsistencias !== []) {
            $this->logger->warning('web_presupuesto_ducha.inconsistencia_budgetflow', [
                'trace_id' => WebDuchaDebugContext::getTraceId(),
                'mensaje' => 'BudgetFlow requiere campos que el orquestador no puede obtener de la definicion actual del configurador.',
                'faltantes_budgetflow' => $faltantesBudgetFlow,
                'inconsistencias' => $inconsistencias,
                'campos_extraidos_ids' => array_map(static fn(array $campo): string => $campo['id'], $estado['campos']),
            ]);
        }

        $this->logger->debug('web_presupuesto_ducha.diagnostico_interaccion', [
            'trace_id' => WebDuchaDebugContext::getTraceId(),
            'configurador' => $estado['configurador_codigo'] ?? null,
            'valores_actuales' => $estado['valores'],
            'campos_extraidos_configuracion' => $this->camposParaDiagnostico($estado['campos']),
            'campos_resueltos' => $this->camposResueltos($estado['campos'], $estado['valores']),
            'campos_pendientes_por_configuracion' => array_map(static fn(array $campo): string => $campo['id'], $pendientesConfiguracion),
            'validacion_budgetflow' => $validacion,
            'errores_budgetflow' => $validacion['errores'] ?? [],
            'campos_que_budgetflow_declara_faltantes' => $faltantesBudgetFlow,
            'campos_pendientes_finales' => array_map(static fn(array $campo): string => $campo['id'], $pendientesFinales),
            'campos_elegidos_siguiente_pregunta' => array_map(static fn(array $campo): string => $campo['id'], $grupo),
            'respuesta_ia_generar_siguiente_pregunta' => $preguntaDiagnostico['ia_respuesta'],
            'pregunta_generada' => $preguntaDiagnostico['pregunta'],
            'fuente_pregunta' => $preguntaDiagnostico['fuente'],
            'motivo_fallback' => $preguntaDiagnostico['fallback_motivo'],
        ]);
    }

    private function camposParaDiagnostico(array $campos): array
    {
        return array_map(static fn(array $campo): array => [
            'id' => $campo['id'] ?? null,
            'componente' => $campo['componente'] ?? null,
            'campo' => $campo['codigo'] ?? null,
            'tipo' => $campo['tipo'] ?? null,
            'obligatorio' => $campo['obligatorio'] ?? null,
            'default' => $campo['valorDefecto'] ?? null,
            'unidad' => $campo['unidad'] ?? null,
            'opciones' => $campo['opciones'] ?? [],
            'aplicabilidad' => $campo['aplicabilidad'] ?? null,
            'restricciones' => $campo['restricciones'] ?? null,
        ], $campos);
    }

    private function camposResueltos(array $campos, array $valores): array
    {
        $resueltos = [];

        foreach ($campos as $campo) {
            if (array_key_exists($campo['codigo'], $valores[$campo['componente']] ?? [])) {
                $resueltos[] = $campo['id'];
            }
        }

        return $resueltos;
    }

    private function faltantesDesdeErrores(array $errores): array
    {
        $faltantes = [];

        foreach ($errores as $error) {
            if (!is_array($error)) {
                continue;
            }

            $configurador = $error['configurador'] ?? null;
            $campo = $error['campo'] ?? null;

            if (!$configurador || !$campo) {
                continue;
            }

            $faltantes[] = [
                'id' => $configurador.'.'.$campo,
                'configurador' => $configurador,
                'campo' => $campo,
                'codigo_error' => $error['codigo'] ?? null,
                'mensaje' => $error['mensaje'] ?? null,
            ];
        }

        return $faltantes;
    }

    private function inconsistenciasBudgetFlow(array $campos, array $faltantesBudgetFlow): array
    {
        $idsDisponibles = array_flip(array_map(static fn(array $campo): string => $campo['id'], $campos));
        $inconsistencias = [];

        foreach ($faltantesBudgetFlow as $faltante) {
            if (!isset($idsDisponibles[$faltante['id']])) {
                $inconsistencias[] = $faltante + [
                    'motivo' => 'campo_no_existe_en_campos_extraidos',
                ];
            }
        }

        return $inconsistencias;
    }

    private function valorPermitido(array $campo, mixed $valor): bool
    {
        $opciones = $campo['opciones'] ?? [];

        if ($opciones === []) {
            return true;
        }

        foreach ($opciones as $opcion) {
            if (($opcion['codigo'] ?? null) === $valor) {
                return true;
            }
        }

        return false;
    }

    private function normalizarValor(array $campo, mixed $valor): mixed
    {
        return match ($campo['tipo'] ?? 'texto') {
            'entero' => is_numeric($valor) ? (int) round((float) $valor) : $valor,
            'decimal' => is_numeric($valor) ? (float) $valor : $valor,
            'booleano' => in_array($valor, [true, 1, '1', 'true', 'si', 'sí'], true),
            default => $valor,
        };
    }

    private function esGrupoMedidas(array $grupo): bool
    {
        if (count($grupo) < 2) {
            return false;
        }

        $codigos = array_column($grupo, 'codigo');

        return in_array('ancho', $codigos, true)
            && in_array('largo', $codigos, true)
            && ($grupo[0]['unidad'] ?? null) === 'cm';
    }

    private function preguntaFallback(array $grupo): string
    {
        if (count($grupo) === 1) {
            $campo = $grupo[0];
            $ayuda = $campo['ayuda'] ?? null;

            return $ayuda
                ? sprintf('%s. %s', $campo['etiqueta'], $ayuda)
                : sprintf('Indica %s para continuar.', mb_strtolower($campo['etiqueta']));
        }

        $labels = array_map(static fn(array $campo): string => mb_strtolower($campo['etiqueta']), $grupo);

        return 'Indica '.implode(' y ', $labels).' para continuar.';
    }

    private function explicacionDesdeMetadatos(array $grupo): string
    {
        $partes = [];

        foreach ($grupo as $campo) {
            $linea = (string) ($campo['etiqueta'] ?? $campo['codigo']);

            if (!empty($campo['ayuda'])) {
                $linea .= ': '.$campo['ayuda'];
            }

            $opciones = $this->opcionesDescritas($campo);

            if ($opciones !== []) {
                $linea .= ' Opciones: '.implode('; ', $opciones).'.';
            }

            $partes[] = $linea;
        }

        return implode(' ', $partes).' ¿Cuál encaja mejor?';
    }

    private function preguntaConSalidaClienteNoSabe(array $grupo): string
    {
        $opciones = [];

        foreach ($grupo as $campo) {
            $opciones = array_merge($opciones, $this->opcionesDescritas($campo));
        }

        if ($opciones === []) {
            return $this->explicacionDesdeMetadatos($grupo).' Si no puedes confirmarlo ahora, elige "No lo sé / Prefiero que me recomendéis".';
        }

        return 'Estas son las opciones válidas: '.implode('; ', $opciones).'. También puedes elegir "No lo sé / Prefiero que me recomendéis".';
    }

    private function opcionesDescritas(array $campo): array
    {
        return array_values(array_map(
            static function (array $opcion): string {
                $texto = (string) ($opcion['etiqueta'] ?? ($opcion['codigo'] ?? ''));
                $descripcion = $opcion['descripcion'] ?? ($opcion['ayuda'] ?? null);

                if (is_string($descripcion) && trim($descripcion) !== '') {
                    $texto .= ' ('.trim($descripcion).')';
                }

                return $texto;
            },
            array_filter($campo['opciones'] ?? [], 'is_array')
        ));
    }

    private function opcionesParaGrupo(array $grupo, bool $incluirNoLoSe = false): array
    {
        if (count($grupo) !== 1) {
            return $incluirNoLoSe ? [[
                'campo' => $grupo[0]['id'] ?? '__grupo__',
                'valor' => self::SELECCION_CLIENTE_NO_PUEDE_DECIDIR,
                'etiqueta' => 'No lo sé / Prefiero que me recomendéis',
                'accion' => self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR,
            ]] : [];
        }

        $campo = $grupo[0];

        $opciones = array_map(
            static fn(array $opcion): array => [
                'campo' => $campo['id'],
                'valor' => $opcion['codigo'] ?? null,
                'etiqueta' => $opcion['etiqueta'] ?? ($opcion['codigo'] ?? ''),
                'descripcion' => $opcion['descripcion'] ?? ($opcion['ayuda'] ?? null),
            ],
            $campo['opciones'] ?? []
        );

        if ($incluirNoLoSe) {
            $opciones[] = [
                'campo' => $campo['id'],
                'valor' => self::SELECCION_CLIENTE_NO_PUEDE_DECIDIR,
                'etiqueta' => 'No lo sé / Prefiero que me recomendéis',
                'accion' => self::SITUACION_CLIENTE_NO_PUEDE_DECIDIR,
            ];
        }

        return $opciones;
    }

    private function camposParaIa(array $campos): array
    {
        return array_map(static fn(array $campo): array => [
            'id' => $campo['id'],
            'codigo' => $campo['codigo'],
            'etiqueta' => $campo['etiqueta'],
            'tipo' => $campo['tipo'],
            'unidad' => $campo['unidad'] ?? null,
            'ayuda' => $campo['ayuda'] ?? null,
            'opciones' => $campo['opciones'] ?? [],
        ], $campos);
    }

    private function pendientesDesdeErrores(array $estado, array $errores): array
    {
        $pendientes = [];

        foreach ($errores as $error) {
            $configurador = $error['configurador'] ?? null;
            $campoCodigo = $error['campo'] ?? null;

            foreach ($estado['campos'] as $campo) {
                if ($this->estadoCampo($estado, $campo) === self::ESTADO_CAMPO_APLAZADO) {
                    continue;
                }

                if (in_array($campo['id'], $estado['campos_omitidos'] ?? [], true)) {
                    continue;
                }

                if ($campo['componente'] === $configurador && $campo['codigo'] === $campoCodigo) {
                    $pendientes[] = $campo;
                }
            }
        }

        return $pendientes;
    }

    private function normalizarResultado(array $resultado): array
    {
        $lineas = array_values(array_filter($resultado['lineas'] ?? [], 'is_array'));
        $total = array_sum(array_map(static fn(array $linea): float => (float) ($linea['importeTotal'] ?? 0), $lineas));

        return [
            'validacion' => $resultado['validacion'] ?? null,
            'lineas' => $lineas,
            'avisos' => $resultado['avisos'] ?? [],
            'total' => round($total, 2),
        ];
    }
}
