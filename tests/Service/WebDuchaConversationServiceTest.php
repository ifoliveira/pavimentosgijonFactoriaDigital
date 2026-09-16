<?php

namespace App\Tests\Service;

use App\Service\WebPresupuesto\WebDuchaBudgetFlowProviderInterface;
use App\Service\WebPresupuesto\WebDuchaCampoExtractor;
use App\Service\WebPresupuesto\WebDuchaConversationService;
use App\Service\WebPresupuesto\WebPresupuestoAiClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class WebDuchaConversationServiceTest extends TestCase
{
    public function testCargaWebDuchaYPreguntaSoloCamposObligatoriosSinDefault(): void
    {
        $service = $this->service();

        $response = $service->iniciar();

        self::assertTrue($response['ok']);
        self::assertFalse($response['finalizada']);
        self::assertSame('web_ducha', $response['estado']['configurador_codigo']);
        self::assertSame([
            'selector_plato_ducha.largo',
            'selector_plato_ducha.ancho',
        ], $response['estado']['campos_pendientes_actuales']);
        self::assertStringContainsString('160 x 70', $response['pregunta']);
    }

    public function testCampoOpcionalConDefaultNoSePregunta(): void
    {
        $response = $this->service()->iniciar();

        $ids = array_column($response['campos'], 'id');

        self::assertNotContains('selector_plato_ducha.color', $response['estado']['campos_pendientes_actuales']);
        self::assertNotContains('selector_plato_ducha.color', $ids);
    }

    public function testInterpretaMedidasEnMetrosYCentimetros(): void
    {
        $service = $this->service();
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '1,60 de largo y unos 70 de ancho');

        self::assertTrue($response['ok']);
        self::assertTrue($response['finalizada']);
        self::assertSame(160, $response['estado']['valores']['selector_plato_ducha']['largo']);
        self::assertSame(70, $response['estado']['valores']['selector_plato_ducha']['ancho']);
        self::assertSame(397.61, $response['resultado']['total']);
    }

    public function testCampoNumericoUnicoPendienteAceptaNumeroSinIa(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoNumericoUnico()), $ai);
        $start = $service->iniciar();

        self::assertSame(['web_mampara.ancho_frente'], $start['estado']['campos_pendientes_actuales']);

        $response = $service->responder($start['estado'], '170');

        self::assertTrue($response['finalizada']);
        self::assertSame(170.0, $response['estado']['valores']['web_mampara']['ancho_frente']);
        self::assertSame(0, $ai->interpretaciones);
    }

    public function testCampoNumericoUnicoPendienteConvierteMetrosACentimetros(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoNumericoUnico()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '1,70 m');

        self::assertTrue($response['finalizada']);
        self::assertSame(170.0, $response['estado']['valores']['web_mampara']['ancho_frente']);
    }

    /**
     * @dataProvider respuestasCentimetrosProvider
     */
    public function testCampoNumericoUnicoPendienteAceptaCentimetrosConSufijo(string $respuesta): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoNumericoUnico()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], $respuesta);

        self::assertTrue($response['finalizada']);
        self::assertSame(170.0, $response['estado']['valores']['web_mampara']['ancho_frente']);
    }

    public function respuestasCentimetrosProvider(): array
    {
        return [
            ['170 cm'],
            ['170cm'],
        ];
    }

    public function testRespuestaAmbiguaRequiereAclaracion(): void
    {
        $service = $this->service();
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], 'es bastante grande');

        self::assertTrue($response['ok']);
        self::assertFalse($response['finalizada']);
        self::assertSame([], $response['estado']['valores']);
        self::assertStringContainsString('medidas', mb_strtolower($response['pregunta']));
    }

    public function testRespuestaValidaReseteaContadorSinProgreso(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion()));
        $start = $service->iniciar();
        $bloqueada = $service->responder($start['estado'], 'no entiendo');

        self::assertSame(1, $bloqueada['intentos_sin_progreso']);

        $response = $service->responder($bloqueada['estado'], null, [
            'selector_plato_ducha.color' => 'blanco',
        ]);

        $key = implode('|', $bloqueada['estado']['campos_pendientes_actuales']);
        self::assertSame(0, $response['estado']['intentos_sin_progreso'][$key]);
        self::assertTrue($response['finalizada']);
    }

    public function testNoEntiendoExplicaConMetadatosSinRepetirPreguntaSimple(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccionDescrito()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], 'no entiendo la pregunta');

        self::assertFalse($response['finalizada']);
        self::assertSame('NO_ENTIENDE', $response['situacion']);
        self::assertStringContainsString('Color', $response['pregunta']);
        self::assertStringContainsString('Color claro proporcionado por BudgetFlow', $response['pregunta']);
        self::assertStringNotContainsString('Pregunta generada por IA', $response['pregunta']);
    }

    public function testPonmeUnEjemploUsaExplicacionDeBudgetFlow(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccionDescrito()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], 'ponme un ejemplo de esto');

        self::assertSame('NO_ENTIENDE', $response['situacion']);
        self::assertStringContainsString('Opciones:', $response['pregunta']);
        self::assertStringContainsString('Color oscuro proporcionado por BudgetFlow', $response['pregunta']);
    }

    public function testNoSeQueElegirDetectaNoSabeElegir(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], 'no se que elegir');

        self::assertSame('NO_SABE_ELEGIR', $response['situacion']);
        self::assertSame(1, $response['intentos_sin_progreso']);
    }

    public function testSegundoIntentoSinProgresoOfreceNoLoSe(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccionDescrito()));
        $start = $service->iniciar();

        $uno = $service->responder($start['estado'], 'no entiendo');
        $dos = $service->responder($uno['estado'], 'no se que elegir');

        self::assertSame(2, $dos['intentos_sin_progreso']);
        self::assertStringContainsString('No lo sé / Prefiero que me recomendéis', $dos['pregunta']);
        self::assertContains('No lo sé / Prefiero que me recomendéis', array_column($dos['opciones'], 'etiqueta'));
    }

    public function testLimiteDeIntentosProduceSalidaControladaSinInventarValor(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion()));
        $start = $service->iniciar();

        $uno = $service->responder($start['estado'], 'no entiendo');
        $dos = $service->responder($uno['estado'], 'no se que elegir');
        $tres = $service->responder($dos['estado'], 'sigo sin saber');

        self::assertTrue($tres['incompleto']);
        self::assertSame('INCOMPLETO_POR_DATOS_APLAZADOS', $tres['estado_conversacion']);
        self::assertSame('CLIENTE_NO_PUEDE_DECIDIR', $tres['situacion']);
        self::assertArrayNotHasKey('selector_plato_ducha', $tres['estado']['valores']);
        self::assertSame(['selector_plato_ducha.color'], $tres['campos_aplazados']);
        self::assertSame('selector_plato_ducha.color', $tres['decisiones_pendientes'][0]['id']);
    }

    public function testClienteNoPuedeDecidirUsaDefaultSiExiste(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoObligatorioConDefaultForzado()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.color' => '__cliente_no_puede_decidir__',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertSame('blanco', $response['estado']['valores']['selector_plato_ducha']['color']);
    }

    public function testClienteNoPuedeDecidirOmiteCampoOpcional(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoOpcionalForzado()));
        $start = $service->iniciar();
        $estado = $start['estado'];
        $estado['campos_pendientes_actuales'] = ['selector_plato_ducha.color'];

        $response = $service->responder($estado, null, [
            'selector_plato_ducha.color' => '__cliente_no_puede_decidir__',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertSame(['selector_plato_ducha.color'], $response['estado']['campos_omitidos']);
        self::assertArrayNotHasKey('selector_plato_ducha', $response['estado']['valores']);
    }

    public function testClienteNoPuedeDecidirEnCampoObligatorioQuedaPendiente(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.color' => '__cliente_no_puede_decidir__',
        ]);

        self::assertTrue($response['incompleto']);
        self::assertSame('datos_aplazados', $response['motivo']);
        self::assertSame('APLAZADO_POR_CLIENTE', $response['estado']['estado_campos']['selector_plato_ducha.color']);
        self::assertSame('selector_plato_ducha.color', $response['decisiones_pendientes'][0]['id']);
        self::assertArrayNotHasKey('selector_plato_ducha', $response['estado']['valores']);
    }

    public function testBugExactoAplazaGrupoMedidasYAvanzaAlSiguienteCampo(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConMedidasYSeleccion()));
        $start = $service->iniciar();

        self::assertSame([
            'selector_plato_ducha.largo',
            'selector_plato_ducha.ancho',
        ], $start['estado']['campos_pendientes_actuales']);

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.largo' => '__cliente_no_puede_decidir__',
        ]);

        self::assertFalse($response['finalizada']);
        self::assertSame([
            'selector_plato_ducha.largo',
            'selector_plato_ducha.ancho',
        ], $response['estado']['campos_aplazados']);
        self::assertSame('APLAZADO_POR_CLIENTE', $response['estado']['estado_campos']['selector_plato_ducha.largo']);
        self::assertSame('APLAZADO_POR_CLIENTE', $response['estado']['estado_campos']['selector_plato_ducha.ancho']);
        self::assertSame(['selector_plato_ducha.tipo_mampara'], $response['estado']['campos_pendientes_actuales']);
        self::assertStringNotContainsString('160 x 70', $response['pregunta']);
        self::assertStringNotContainsString('Dejaremos este punto pendiente', $response['pregunta']);
        self::assertArrayNotHasKey('selector_plato_ducha', $response['estado']['valores']);

        $siguiente = $service->responder($response['estado'], 'ok, siguiente');

        self::assertSame(['selector_plato_ducha.tipo_mampara'], $siguiente['estado']['campos_pendientes_actuales']);
        self::assertStringNotContainsString('160 x 70', $siguiente['pregunta']);
        self::assertStringNotContainsString('Dejaremos este punto pendiente', $siguiente['pregunta']);
    }

    public function testTodosLosCamposPreguntablesAgotadosConAplazadosTerminaIncompleto(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBase());
        $service = $this->service($provider);
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.largo' => '__cliente_no_puede_decidir__',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertTrue($response['incompleto']);
        self::assertSame('INCOMPLETO_POR_DATOS_APLAZADOS', $response['estado']['estado_conversacion']);
        self::assertSame([
            'selector_plato_ducha.largo',
            'selector_plato_ducha.ancho',
        ], $response['campos_aplazados']);
        self::assertSame([], $response['estado']['campos_pendientes_actuales']);
        self::assertSame([], $response['estado']['valores']);
        self::assertSame(0, $provider->generaciones);
    }

    public function testAplazadoConValorProvisionalPuedeCalcular(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConMedidasYSeleccion());
        $service = $this->service($provider, null, $this->valoresProvisionalesBase());
        $start = $service->iniciar();
        $aplazado = $service->responder($start['estado'], null, [
            'selector_plato_ducha.largo' => '__cliente_no_puede_decidir__',
        ]);

        $response = $service->responder($aplazado['estado'], null, [
            'selector_plato_ducha.tipo_mampara' => 'fijo',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertTrue($response['contiene_estimaciones']);
        self::assertSame(170, $provider->generacionesRecibidas[0]['selector_plato_ducha']['largo']);
        self::assertSame(70, $provider->generacionesRecibidas[0]['selector_plato_ducha']['ancho']);
        self::assertSame('fijo', $provider->generacionesRecibidas[0]['selector_plato_ducha']['tipo_mampara']);
        self::assertArrayNotHasKey('largo', $response['estado']['valores']['selector_plato_ducha']);
        self::assertArrayNotHasKey('ancho', $response['estado']['valores']['selector_plato_ducha']);
        self::assertSame('APLAZADO_POR_CLIENTE', $response['estado']['estado_campos']['selector_plato_ducha.largo']);
        self::assertSame('APLAZADO_POR_CLIENTE', $response['estado']['estado_campos']['selector_plato_ducha.ancho']);
        self::assertSame([
            [
                'campo' => 'selector_plato_ducha.ancho',
                'componente' => 'selector_plato_ducha',
                'codigo' => 'ancho',
                'valor' => 70,
                'origen' => 'ESTIMACION',
            ],
            [
                'campo' => 'selector_plato_ducha.largo',
                'componente' => 'selector_plato_ducha',
                'codigo' => 'largo',
                'valor' => 170,
                'origen' => 'ESTIMACION',
            ],
        ], $response['valores_estimados']);
    }

    public function testCampoResueltoPorClientePrevaleceSobreValorProvisional(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBase());
        $service = $this->service($provider, null, $this->valoresProvisionalesBase());
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '160 x 75');

        self::assertTrue($response['finalizada']);
        self::assertFalse($response['contiene_estimaciones']);
        self::assertSame(160, $provider->generacionesRecibidas[0]['selector_plato_ducha']['largo']);
        self::assertSame(75, $provider->generacionesRecibidas[0]['selector_plato_ducha']['ancho']);
        self::assertSame([], $response['valores_estimados']);
    }

    public function testValorProvisionalNuncaSobrescribeValorRealEnMezcla(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConMedidasYSeleccion());
        $service = $this->service($provider, null, $this->valoresProvisionalesBase());
        $start = $service->iniciar();
        $estado = $start['estado'];
        $estado['valores']['selector_plato_ducha']['largo'] = 180;
        $estado['estado_campos']['selector_plato_ducha.largo'] = 'RESUELTO';
        $estado['campos_aplazados'] = ['selector_plato_ducha.ancho'];
        $estado['estado_campos']['selector_plato_ducha.ancho'] = 'APLAZADO_POR_CLIENTE';
        $estado['campos_pendientes_actuales'] = ['selector_plato_ducha.tipo_mampara'];

        $response = $service->responder($estado, null, [
            'selector_plato_ducha.tipo_mampara' => 'corredera',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertTrue($response['contiene_estimaciones']);
        self::assertSame(180, $provider->generacionesRecibidas[0]['selector_plato_ducha']['largo']);
        self::assertSame(70, $provider->generacionesRecibidas[0]['selector_plato_ducha']['ancho']);
        self::assertSame([
            [
                'campo' => 'selector_plato_ducha.ancho',
                'componente' => 'selector_plato_ducha',
                'codigo' => 'ancho',
                'valor' => 70,
                'origen' => 'ESTIMACION',
            ],
        ], $response['valores_estimados']);
    }

    public function testValorInvalidoDelClienteNoActivaValorProvisional(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBase());
        $provider->forzarCampoInvalido = ['selector_plato_ducha', 'largo'];
        $service = $this->service($provider, null, $this->valoresProvisionalesBase());
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '1800 x 70');

        self::assertFalse($response['finalizada']);
        self::assertSame(0, $provider->generaciones);
        self::assertSame(1800, $response['estado']['valores']['selector_plato_ducha']['largo']);
        self::assertSame([], $response['estado']['campos_aplazados']);
    }

    public function testAplazarCuentaComoProgresoConversacional(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConMedidasYSeleccion()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.largo' => '__cliente_no_puede_decidir__',
        ]);

        self::assertSame(['selector_plato_ducha.tipo_mampara'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(0, $response['intentos_sin_progreso']);
    }

    public function testCampoAplazadoPuedeReactivarseConceptualmente(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configBase()));
        $start = $service->iniciar();
        $incompleto = $service->responder($start['estado'], null, [
            'selector_plato_ducha.largo' => '__cliente_no_puede_decidir__',
        ]);

        $estado = $incompleto['estado'];
        $estado['campos_aplazados'] = [];
        $estado['estado_campos']['selector_plato_ducha.largo'] = 'PENDIENTE';
        $estado['estado_campos']['selector_plato_ducha.ancho'] = 'PENDIENTE';
        $estado['campos_pendientes_actuales'] = [
            'selector_plato_ducha.largo',
            'selector_plato_ducha.ancho',
        ];
        $estado['finalizada'] = false;
        $estado['estado_conversacion'] = 'EN_CURSO';

        $response = $service->responder($estado, '160 x 70');

        self::assertTrue($response['finalizada']);
        self::assertSame(160, $response['estado']['valores']['selector_plato_ducha']['largo']);
        self::assertSame(70, $response['estado']['valores']['selector_plato_ducha']['ancho']);
    }

    public function testDescripcionOpcionalDeOpcionesViajaAIYFrontend(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccionDescrito()), $ai);
        $start = $service->iniciar();

        self::assertSame('Color claro proporcionado por BudgetFlow', $start['opciones'][0]['descripcion']);

        $service->responder($start['estado'], 'otra cosa');

        self::assertSame('Color claro proporcionado por BudgetFlow', $ai->ultimosCamposInterpretacion[0]['opciones'][0]['descripcion']);
    }

    public function testImpideOpcionNoDefinidaPorBudgetFlow(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion());
        $service = $this->service($provider);
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.color' => 'negro',
        ]);

        self::assertArrayNotHasKey('selector_plato_ducha', $response['estado']['valores']);
        self::assertFalse($response['finalizada']);
    }

    public function testSeleccionValidaConBotonNoNecesitaIa(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion());
        $service = $this->service($provider, $ai);
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.color' => 'blanco',
        ]);

        self::assertSame('blanco', $response['estado']['valores']['selector_plato_ducha']['color']);
        self::assertSame(0, $ai->interpretaciones);
    }

    public function testValidacionPosteriorContraBudgetFlowImpideGenerarSiFaltaObligatorio(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBase());
        $provider->forzarInvalido = true;
        $service = $this->service($provider);
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '160 x 70');

        self::assertFalse($response['finalizada']);
        self::assertSame(0, $provider->generaciones);
    }

    public function testBudgetFlowNoDisponibleDevuelveErrorControlado(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBase());
        $provider->fallarConfigurador = true;

        $response = $this->service($provider)->iniciar();

        self::assertFalse($response['ok']);
        self::assertStringContainsString('iniciar', $response['error']);
    }

    public function testIaNoDisponibleUsaFallbackDeterminista(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->fallarPregunta = true;
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoSeleccion()), $ai);

        $response = $service->iniciar();

        self::assertStringContainsString('color', mb_strtolower($response['pregunta']));
    }

    public function testPreguntasCondicionalesProcedenDeValidacionBudgetFlow(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConGriferiaCondicional());
        $provider->activarCondicionGriferia = true;
        $service = $this->service($provider);
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], null, [
            'selector_plato_ducha.cambiar_griferia' => 'si',
        ]);

        self::assertFalse($response['finalizada']);
        self::assertSame(['selector_plato_ducha.tipo_griferia'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testTipoDeCampoNoSoportadoNoRompeConversacion(): void
    {
        $config = $this->configBase();
        $config['componentes'][0]['configurador']['campos'][0]['tipo'] = 'tipo_raro';
        $config['componentes'][0]['configurador']['campos'] = [$config['componentes'][0]['configurador']['campos'][0]];
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($config));

        $response = $service->iniciar();

        self::assertTrue($response['ok']);
        self::assertFalse($response['finalizada']);
    }

    public function testPresupuestadorAntiguoNoFormaParteDelNuevoServicio(): void
    {
        $reflection = new \ReflectionClass(WebDuchaConversationService::class);
        $constructor = $reflection->getConstructor();

        $types = array_map(
            static fn(\ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $constructor?->getParameters() ?? []
        );

        self::assertNotContains('App\Service\PresupuestoCalculatorService', $types);
    }

    private function service(
        ?FakeWebDuchaBudgetFlowProvider $provider = null,
        ?FakeWebPresupuestoAiClient $ai = null,
        array $valoresProvisionales = []
    ): WebDuchaConversationService {
        return new WebDuchaConversationService(
            $provider ?? new FakeWebDuchaBudgetFlowProvider($this->configBase()),
            $ai ?? new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            $valoresProvisionales,
        );
    }

    private function configBase(): array
    {
        return [
            'codigo' => 'web_ducha',
            'nombre' => 'Cambio plato de ducha desde Web',
            'tipo' => 'compuesto',
            'version' => 1,
            'campos' => [],
            'componentes' => [
                [
                    'codigo' => 'selector_plato_ducha',
                    'orden' => 1,
                    'obligatorio' => true,
                    'configurador' => [
                        'codigo' => 'selector_plato_ducha',
                        'tipo' => 'simple',
                        'campos' => [
                            [
                                'codigo' => 'ancho',
                                'etiqueta' => 'Ancho del plato de ducha',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 1,
                                'ayuda' => 'Ancho del plato expresado en centimetros.',
                                'opciones' => [],
                            ],
                            [
                                'codigo' => 'largo',
                                'etiqueta' => 'Largo del plato de ducha',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 2,
                                'ayuda' => 'Largo del plato expresado en centimetros.',
                                'opciones' => [],
                            ],
                            [
                                'codigo' => 'color',
                                'etiqueta' => 'Color',
                                'tipo' => 'seleccion',
                                'obligatorio' => false,
                                'orden' => 3,
                                'valorDefecto' => 'blanco',
                                'opciones' => [
                                    ['codigo' => 'blanco', 'etiqueta' => 'Blanco'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function configConCampoSeleccion(): array
    {
        $config = $this->configBase();
        $config['componentes'][0]['configurador']['campos'] = [
            [
                'codigo' => 'color',
                'etiqueta' => 'Color',
                'tipo' => 'seleccion',
                'obligatorio' => true,
                'orden' => 1,
                'opciones' => [
                    ['codigo' => 'blanco', 'etiqueta' => 'Blanco'],
                    ['codigo' => 'gris', 'etiqueta' => 'Gris'],
                ],
            ],
        ];

        return $config;
    }

    private function configConCampoSeleccionDescrito(): array
    {
        $config = $this->configConCampoSeleccion();
        $config['componentes'][0]['configurador']['campos'][0]['ayuda'] = 'Selecciona una de las opciones disponibles.';
        $config['componentes'][0]['configurador']['campos'][0]['opciones'][0]['descripcion'] = 'Color claro proporcionado por BudgetFlow';
        $config['componentes'][0]['configurador']['campos'][0]['opciones'][1]['descripcion'] = 'Color oscuro proporcionado por BudgetFlow';

        return $config;
    }

    private function configConMedidasYSeleccion(): array
    {
        $config = $this->configBase();
        $config['componentes'][0]['configurador']['campos'][] = [
            'codigo' => 'tipo_mampara',
            'etiqueta' => 'Tipo de cierre',
            'tipo' => 'seleccion',
            'obligatorio' => true,
            'orden' => 4,
            'opciones' => [
                ['codigo' => 'fijo', 'etiqueta' => 'Cristal fijo'],
                ['codigo' => 'corredera', 'etiqueta' => 'Corredera'],
            ],
        ];

        unset($config['componentes'][0]['configurador']['campos'][2]['valorDefecto']);
        $config['componentes'][0]['configurador']['campos'][2]['obligatorio'] = false;

        return $config;
    }

    private function configConCampoObligatorioConDefaultForzado(): array
    {
        $config = $this->configConCampoSeleccion();
        $config['componentes'][0]['configurador']['campos'][0]['valorDefecto'] = 'blanco';

        return $config;
    }

    private function configConCampoOpcionalForzado(): array
    {
        $config = $this->configConCampoSeleccion();
        $config['componentes'][0]['configurador']['campos'][0]['obligatorio'] = false;

        return $config;
    }

    private function configConGriferiaCondicional(): array
    {
        $config = $this->configBase();
        $config['componentes'][0]['configurador']['campos'] = [
            [
                'codigo' => 'cambiar_griferia',
                'etiqueta' => 'Cambiar griferia',
                'tipo' => 'seleccion',
                'obligatorio' => true,
                'orden' => 1,
                'opciones' => [
                    ['codigo' => 'si', 'etiqueta' => 'Si'],
                    ['codigo' => 'no', 'etiqueta' => 'No'],
                ],
            ],
            [
                'codigo' => 'tipo_griferia',
                'etiqueta' => 'Tipo de griferia',
                'tipo' => 'seleccion',
                'obligatorio' => false,
                'orden' => 2,
                'opciones' => [
                    ['codigo' => 'normal', 'etiqueta' => 'Normal'],
                    ['codigo' => 'termostatica', 'etiqueta' => 'Termostatica'],
                ],
            ],
        ];

        return $config;
    }

    private function configConCampoNumericoUnico(): array
    {
        return [
            'codigo' => 'web_ducha',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'web_mampara',
                    'orden' => 1,
                    'obligatorio' => true,
                    'configurador' => [
                        'codigo' => 'web_mampara',
                        'tipo' => 'simple',
                        'campos' => [
                            [
                                'codigo' => 'ancho_frente',
                                'etiqueta' => 'Ancho del frente',
                                'tipo' => 'decimal',
                                'obligatorio' => true,
                                'orden' => 1,
                                'ayuda' => 'Medida real del hueco del frente en centimetros.',
                                'opciones' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function valoresProvisionalesBase(): array
    {
        return [
            'web_ducha' => [
                'selector_plato_ducha.largo' => 170,
                'selector_plato_ducha.ancho' => 70,
            ],
        ];
    }
}

final class FakeWebDuchaBudgetFlowProvider implements WebDuchaBudgetFlowProviderInterface
{
    public bool $fallarConfigurador = false;
    public bool $forzarInvalido = false;
    public ?array $forzarCampoInvalido = null;
    public bool $activarCondicionGriferia = false;
    public int $generaciones = 0;
    public array $validacionesRecibidas = [];
    public array $generacionesRecibidas = [];

    public function __construct(private readonly array $configurador)
    {
    }

    public function obtenerConfigurador(): array
    {
        if ($this->fallarConfigurador) {
            throw new \RuntimeException('BudgetFlow offline');
        }

        return $this->configurador;
    }

    public function validar(array $configurador, array $valores): array
    {
        $this->validacionesRecibidas[] = $valores;

        if (
            $this->activarCondicionGriferia
            && (($valores['selector_plato_ducha']['cambiar_griferia'] ?? null) === 'si')
            && !array_key_exists('tipo_griferia', $valores['selector_plato_ducha'] ?? [])
        ) {
            return [
                'valido' => false,
                'errores' => [
                    ['configurador' => 'selector_plato_ducha', 'campo' => 'tipo_griferia', 'codigo' => 'campo_obligatorio_condicional'],
                ],
            ];
        }

        if ($this->forzarInvalido || $this->forzarCampoInvalido !== null) {
            $campoInvalido = $this->forzarCampoInvalido ?? ['selector_plato_ducha', 'largo'];

            return [
                'valido' => false,
                'errores' => [
                    ['configurador' => $campoInvalido[0], 'campo' => $campoInvalido[1], 'codigo' => 'campo_obligatorio'],
                ],
            ];
        }

        $faltan = [];

        foreach ($configurador['componentes'] ?? [] as $componente) {
            $codigoComponente = $componente['codigo'];

            foreach (($componente['configurador']['campos'] ?? []) as $campo) {
                if (!($campo['obligatorio'] ?? false)) {
                    continue;
                }

                if (!array_key_exists($campo['codigo'], $valores[$codigoComponente] ?? [])) {
                    $faltan[] = [
                        'configurador' => $codigoComponente,
                        'campo' => $campo['codigo'],
                        'codigo' => 'campo_obligatorio',
                    ];
                }
            }
        }

        return ['valido' => $faltan === [], 'errores' => $faltan];
    }

    public function generar(array $configurador, array $valores): array
    {
        $this->generaciones++;
        $this->generacionesRecibidas[] = $valores;

        return [
            'validacion' => ['valido' => true, 'errores' => []],
            'lineas' => [
                [
                    'descripcion' => 'PLATO ARDESIA 120X80 BLANCO',
                    'cantidad' => 1.0,
                    'unidad' => 'unidad',
                    'importeTotal' => 397.61,
                ],
            ],
            'avisos' => [],
        ];
    }
}

final class FakeWebPresupuestoAiClient implements WebPresupuestoAiClientInterface
{
    public bool $fallarPregunta = false;
    public int $interpretaciones = 0;
    public array $interpretacion = [
        'valores_detectados' => [],
        'requiere_aclaracion' => true,
        'situacion' => 'AMBIGUA',
    ];
    public array $ultimosCamposInterpretacion = [];

    public function generarPregunta(array $campos, array $valoresConocidos): string
    {
        if ($this->fallarPregunta) {
            throw new \RuntimeException('IA offline');
        }

        return 'Pregunta generada por IA';
    }

    public function interpretarRespuesta(array $campos, string $respuesta, array $valoresConocidos): array
    {
        $this->interpretaciones++;
        $this->ultimosCamposInterpretacion = $campos;

        return $this->interpretacion;
    }
}
