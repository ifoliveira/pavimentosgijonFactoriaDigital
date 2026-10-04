<?php

namespace App\Tests\Service;

use App\Integration\BudgetFlow\BudgetFlowClient;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use App\Service\WebPresupuesto\BudgetFlowWebBanoProvider;
use App\Service\WebPresupuesto\WebDuchaBudgetFlowProviderInterface;
use App\Service\WebPresupuesto\WebDuchaCampoExtractor;
use App\Service\WebPresupuesto\WebDuchaConversationService;
use App\Service\WebPresupuesto\WebPresupuestoAiClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Yaml\Yaml;

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

    public function testExtractorTransportaLimitesNumericosDeDefinicion(): void
    {
        $config = $this->configBaseConRangos(100, 1000);
        $campos = (new WebDuchaCampoExtractor())->extraerCampos($config);

        self::assertSame(100, $campos[0]['valorMinimo']);
        self::assertSame(1000, $campos[0]['valorMaximo']);
    }

    public function testGrupoNumericoConRangosValidosSeGuarda(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configBaseConRangos(100, 1000)));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '200 x 300');

        self::assertTrue($response['finalizada']);
        self::assertSame(200, $response['estado']['valores']['selector_plato_ducha']['largo']);
        self::assertSame(300, $response['estado']['valores']['selector_plato_ducha']['ancho']);
    }

    public function testGrupoNumericoPorEncimaDelMaximoNoGuardaValoresDelTurno(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configBaseConRangos(100, 1000)));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '1800 x 2500');

        self::assertFalse($response['finalizada']);
        self::assertArrayNotHasKey('selector_plato_ducha', $response['estado']['valores']);
        self::assertSame([
            'selector_plato_ducha.largo',
            'selector_plato_ducha.ancho',
        ], $response['estado']['campos_pendientes_actuales']);
        self::assertCount(2, $response['estado']['ultima_validacion_conversacional']['rechazos']);
        self::assertStringContainsString('fuera del rango admitido', $response['pregunta']);
    }

    public function testGrupoNumericoConUnValorFueraDeRangoNoGuardaParcialmente(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configBaseConRangos(100, 1000)));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '1800 x 250');

        self::assertFalse($response['finalizada']);
        self::assertArrayNotHasKey('selector_plato_ducha', $response['estado']['valores']);
        self::assertSame('selector_plato_ducha.largo', $response['estado']['ultima_validacion_conversacional']['rechazos'][0]['campo']);
        self::assertSame('superior', $response['estado']['ultima_validacion_conversacional']['rechazos'][0]['motivo']);
    }

    public function testGrupoNumericoAceptaValoresExactamenteEnLosLimites(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configBaseConRangos(100, 1000)));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '100 x 1000');

        self::assertTrue($response['finalizada']);
        self::assertSame(100, $response['estado']['valores']['selector_plato_ducha']['largo']);
        self::assertSame(1000, $response['estado']['valores']['selector_plato_ducha']['ancho']);
    }

    /**
     * @dataProvider respuestasCampoIndividualConRangoProvider
     */
    public function testCampoNumericoIndividualValidaRango(?int $minimo, ?int $maximo, string $respuesta, bool $esperadoValido): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configConCampoNumericoUnico($minimo, $maximo)));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], $respuesta);

        self::assertSame($esperadoValido, $response['finalizada']);

        if ($esperadoValido) {
            self::assertArrayHasKey('ancho_frente', $response['estado']['valores']['web_mampara']);
        } else {
            self::assertArrayNotHasKey('web_mampara', $response['estado']['valores']);
            self::assertSame(['web_mampara.ancho_frente'], $response['estado']['campos_pendientes_actuales']);
            self::assertStringContainsString('fuera del rango admitido', $response['pregunta']);
        }
    }

    public function respuestasCampoIndividualConRangoProvider(): array
    {
        return [
            'inferior al mínimo' => [100, 1000, '99', false],
            'igual al mínimo' => [100, 1000, '100', true],
            'igual al máximo' => [100, 1000, '1000', true],
            'superior al máximo' => [100, 1000, '1001', false],
            'solo mínimo inválido' => [100, null, '99', false],
            'solo mínimo válido límite' => [100, null, '100', true],
            'solo mínimo válido alto' => [100, null, '5000', true],
            'solo máximo válido bajo' => [null, 1000, '1', true],
            'solo máximo válido límite' => [null, 1000, '1000', true],
            'solo máximo inválido' => [null, 1000, '1001', false],
        ];
    }

    public function testSinLimitesMantieneComportamientoAnterior(): void
    {
        $service = $this->service(new FakeWebDuchaBudgetFlowProvider($this->configBase()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '1800 x 2500');

        self::assertTrue($response['finalizada']);
        self::assertSame(1800, $response['estado']['valores']['selector_plato_ducha']['largo']);
        self::assertSame(2500, $response['estado']['valores']['selector_plato_ducha']['ancho']);
    }

    public function testCampoDecimalValidaRangoNumerico(): void
    {
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configCampoDecimalBano(1.5, 3.5)),
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $start = $service->iniciar();
        $valido = $service->responder($start['estado'], '2,5');

        self::assertTrue($valido['finalizada']);
        self::assertSame(2.5, $valido['estado']['valores']['web_medidas_bano']['superficie']);

        $start = $service->iniciar();
        $invalido = $service->responder($start['estado'], '4');

        self::assertFalse($invalido['finalizada']);
        self::assertArrayNotHasKey('web_medidas_bano', $invalido['estado']['valores']);
    }

    public function testValorDerivadoCopiaValorCuandoCondicionSeCumple(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConPlatoYMamparaReal());
        $service = $this->service($provider, null, [], $this->valoresDerivadosMamparaDesdePlato());
        $start = $service->iniciar();
        $medidas = $service->responder($start['estado'], '160 x 80');

        self::assertSame(['web_mampara.tipo'], $medidas['estado']['campos_pendientes_actuales']);

        $response = $service->responder($medidas['estado'], null, [
            'web_mampara.tipo' => 'frente',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertSame(160, $response['estado']['valores']['web_mampara']['ancho_frente']);
        self::assertSame('selector_plato_ducha.largo', $response['estado']['valores_deducidos']['web_mampara']['ancho_frente']['origen']);
        self::assertSame(160, $response['estado']['valores_deducidos']['web_mampara']['ancho_frente']['valor']);
        self::assertSame(160, $provider->generacionesRecibidas[0]['web_mampara']['ancho_frente']);
        self::assertFalse($response['contiene_estimaciones']);
        self::assertSame([], $response['valores_estimados']);
    }

    public function testValorDerivadoNoCopiaSiCondicionNoSeCumple(): void
    {
        $service = $this->service(
            new FakeWebDuchaBudgetFlowProvider($this->configConPlatoYMamparaReal()),
            null,
            [],
            $this->valoresDerivadosMamparaDesdePlato()
        );
        $start = $service->iniciar();
        $medidas = $service->responder($start['estado'], '160 x 80');

        $response = $service->responder($medidas['estado'], null, [
            'web_mampara.tipo' => 'fijo',
        ]);

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_mampara.ancho_frente'], $response['estado']['campos_pendientes_actuales']);
        self::assertArrayNotHasKey('ancho_frente', $response['estado']['valores']['web_mampara']);
    }

    public function testValorDerivadoConOrigenInexistenteNoRompeYNoCopia(): void
    {
        $service = $this->service(
            new FakeWebDuchaBudgetFlowProvider($this->configConPlatoYMamparaReal()),
            null,
            [],
            [
                'web_ducha' => [
                    'web_mampara.ancho_frente' => [
                        'origen' => 'selector_plato_ducha.alto',
                        'condicion' => [
                            'campo' => 'web_mampara.tipo',
                            'valor' => 'frente',
                        ],
                    ],
                ],
            ]
        );
        $start = $service->iniciar();
        $medidas = $service->responder($start['estado'], '160 x 80');

        $response = $service->responder($medidas['estado'], null, [
            'web_mampara.tipo' => 'frente',
        ]);

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_mampara.ancho_frente'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testValorDerivadoNoSobrescribeDestinoYaInformado(): void
    {
        $service = $this->service(
            new FakeWebDuchaBudgetFlowProvider($this->configConPlatoYMamparaReal()),
            null,
            [],
            $this->valoresDerivadosMamparaDesdePlato()
        );
        $start = $service->iniciar();
        $medidas = $service->responder($start['estado'], '160 x 80');
        $estado = $medidas['estado'];
        $estado['valores']['web_mampara']['ancho_frente'] = 158.0;

        $response = $service->responder($estado, null, [
            'web_mampara.tipo' => 'frente',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertSame(158.0, $response['estado']['valores']['web_mampara']['ancho_frente']);
        self::assertArrayNotHasKey('web_mampara', $response['estado']['valores_deducidos']);
    }

    public function testValorDerivadoNoActivaComponenteNoPresente(): void
    {
        $service = $this->service(
            new FakeWebDuchaBudgetFlowProvider($this->configBase()),
            null,
            [],
            $this->valoresDerivadosMamparaDesdePlato()
        );
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '160 x 80');

        self::assertTrue($response['finalizada']);
        self::assertArrayNotHasKey('web_mampara', $response['estado']['valores']);
        self::assertSame([], $response['estado']['valores_deducidos']);
    }

    public function testValorDerivadoConExpresionAritmeticaYCondicionesAnd(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConPlatoMamparaManoObraYRevestimientos());
        $service = $this->service($provider, null, [], $this->valoresDerivadosMetrosAzulejo());
        $estado = [
            'configurador_codigo' => 'web_ducha',
            'configurador' => $this->configConPlatoMamparaManoObraYRevestimientos(),
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($this->configConPlatoMamparaManoObraYRevestimientos()),
            'valores' => [
                'selector_plato_ducha' => ['largo' => 160, 'ancho' => 80],
                'web_mampara' => ['tipo' => 'frente', 'ancho_frente' => 160],
                'web_mano_obra_ducha' => ['tipo_trabajo' => 'banera_plato_zona_ducha'],
                'web_revestimientos' => ['azulejo' => true],
            ],
        ];

        $response = $service->responder($estado, 'continuar');

        self::assertTrue($response['finalizada']);
        self::assertSame(8, $response['estado']['valores']['web_revestimientos']['metros_azulejo']);
        self::assertSame(8, $provider->generacionesRecibidas[0]['web_revestimientos']['metros_azulejo']);
        self::assertSame('((selector_plato_ducha.largo + selector_plato_ducha.ancho + selector_plato_ducha.ancho) / 100) * 2.40', $response['estado']['valores_deducidos']['web_revestimientos']['metros_azulejo']['expresion']);
        self::assertFalse($response['contiene_estimaciones']);
    }

    public function testValorDerivadoDecimalSeNormalizaAlTipoDestinoAntesDeValidar(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConPlatoMamparaManoObraYRevestimientos());
        $service = $this->service($provider, null, [], [
            'web_ducha' => [
                'web_revestimientos.metros_azulejo' => [
                    [
                        'expresion' => '((selector_plato_ducha.largo + selector_plato_ducha.ancho + selector_plato_ducha.ancho) / 100) * 0.60',
                        'condiciones' => [
                            ['campo' => 'web_revestimientos.azulejo', 'valor' => true],
                            ['campo' => 'web_mano_obra_ducha.tipo_trabajo', 'valor' => 'plato_por_plato'],
                            ['campo' => 'web_mampara.tipo', 'valor' => 'frente'],
                        ],
                    ],
                ],
            ],
        ]);
        $estado = [
            'configurador_codigo' => 'web_ducha',
            'configurador' => $this->configConPlatoMamparaManoObraYRevestimientos(),
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($this->configConPlatoMamparaManoObraYRevestimientos()),
            'valores' => [
                'selector_plato_ducha' => ['largo' => 180, 'ancho' => 80],
                'web_mampara' => ['tipo' => 'frente', 'ancho_frente' => 180],
                'web_mano_obra_ducha' => ['tipo_trabajo' => 'plato_por_plato'],
                'web_revestimientos' => ['azulejo' => true],
            ],
        ];

        $response = $service->responder($estado, 'continuar');

        self::assertTrue($response['finalizada']);
        self::assertSame(2, $response['estado']['valores']['web_revestimientos']['metros_azulejo']);
        self::assertSame(2, $provider->generacionesRecibidas[0]['web_revestimientos']['metros_azulejo']);
    }

    public function testValorDerivadoConExpresionNoAplicaSiCondicionNoSeCumple(): void
    {
        $service = $this->service(
            new FakeWebDuchaBudgetFlowProvider($this->configConPlatoMamparaManoObraYRevestimientos()),
            null,
            [],
            $this->valoresDerivadosMetrosAzulejo()
        );
        $estado = [
            'configurador_codigo' => 'web_ducha',
            'configurador' => $this->configConPlatoMamparaManoObraYRevestimientos(),
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($this->configConPlatoMamparaManoObraYRevestimientos()),
            'valores' => [
                'selector_plato_ducha' => ['largo' => 160, 'ancho' => 80],
                'web_mampara' => ['tipo' => 'frente', 'ancho_frente' => 160],
                'web_mano_obra_ducha' => ['tipo_trabajo' => 'banera_plato_zona_ducha'],
                'web_revestimientos' => ['azulejo' => false],
            ],
        ];

        $response = $service->responder($estado, 'continuar');

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_revestimientos.metros_azulejo'], $response['estado']['campos_pendientes_actuales']);
        self::assertArrayNotHasKey('metros_azulejo', $response['estado']['valores']['web_revestimientos']);
    }

    public function testValorDerivadoConExpresionEsperaADatosTodaviaNoDisponibles(): void
    {
        $service = $this->service(
            new FakeWebDuchaBudgetFlowProvider($this->configConPlatoMamparaManoObraYRevestimientos()),
            null,
            [],
            $this->valoresDerivadosMetrosAzulejo()
        );
        $estado = [
            'configurador_codigo' => 'web_ducha',
            'configurador' => $this->configConPlatoMamparaManoObraYRevestimientos(),
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($this->configConPlatoMamparaManoObraYRevestimientos()),
            'valores' => [
                'selector_plato_ducha' => ['largo' => 160, 'ancho' => 80],
                'web_mampara' => ['tipo' => 'frente', 'ancho_frente' => 160],
                'web_revestimientos' => ['azulejo' => true],
            ],
        ];

        $response = $service->responder($estado, 'continuar');

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_mano_obra_ducha.tipo_trabajo'], $response['estado']['campos_pendientes_actuales']);
        self::assertArrayNotHasKey('metros_azulejo', $response['estado']['valores']['web_revestimientos']);
    }

    public function testValorDerivadoUsaPrimeraReglaAplicableParaUnDestino(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configConPlatoMamparaManoObraYRevestimientos());
        $service = $this->service($provider, null, [], [
            'web_ducha' => [
                'web_revestimientos.metros_azulejo' => [
                    [
                        'expresion' => 'selector_plato_ducha.largo / 10',
                        'condiciones' => [
                            ['campo' => 'web_revestimientos.azulejo', 'valor' => true],
                            ['campo' => 'web_mampara.tipo', 'valor' => 'fijo'],
                        ],
                    ],
                    [
                        'expresion' => 'selector_plato_ducha.largo / 100',
                        'condiciones' => [
                            ['campo' => 'web_revestimientos.azulejo', 'valor' => true],
                            ['campo' => 'web_mampara.tipo', 'valor' => 'frente'],
                        ],
                    ],
                ],
            ],
        ]);
        $estado = [
            'configurador_codigo' => 'web_ducha',
            'configurador' => $this->configConPlatoMamparaManoObraYRevestimientos(),
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($this->configConPlatoMamparaManoObraYRevestimientos()),
            'valores' => [
                'selector_plato_ducha' => ['largo' => 160, 'ancho' => 80],
                'web_mampara' => ['tipo' => 'frente', 'ancho_frente' => 160],
                'web_mano_obra_ducha' => ['tipo_trabajo' => 'plato_por_plato'],
                'web_revestimientos' => ['azulejo' => true],
            ],
        ];

        $response = $service->responder($estado, 'continuar');

        self::assertTrue($response['finalizada']);
        self::assertSame(2, $response['estado']['valores']['web_revestimientos']['metros_azulejo']);
        self::assertSame(1, $response['estado']['valores_deducidos']['web_revestimientos']['metros_azulejo']['regla']);
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

    public function testPuedeIniciarFlujoBanoConConfiguradorWebBano(): void
    {
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configBanoBase()),
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
        );

        $response = $service->iniciar();

        self::assertTrue($response['ok']);
        self::assertSame('web_baño', $response['estado']['configurador_codigo']);
        self::assertSame(['web_bano_medidas.largo', 'web_bano_medidas.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testEstadosDuchaYBanoSonIndependientes(): void
    {
        $ducha = $this->service();
        $bano = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configBanoBase()),
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
        );

        $estadoDucha = $ducha->iniciar()['estado'];
        $estadoBano = $bano->iniciar()['estado'];

        self::assertSame('web_ducha', $estadoDucha['configurador_codigo']);
        self::assertSame('web_baño', $estadoBano['configurador_codigo']);
        self::assertSame(['selector_plato_ducha.largo', 'selector_plato_ducha.ancho'], $estadoDucha['campos_pendientes_actuales']);
        self::assertSame(['web_bano_medidas.largo', 'web_bano_medidas.ancho'], $estadoBano['campos_pendientes_actuales']);
    }

    public function testValoresProvisionalesSeSeleccionanPorConfiguradorRaiz(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBanoConMedidasYSeleccion());
        $service = new WebDuchaConversationService(
            $provider,
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [
                'web_ducha' => [
                    'web_bano_medidas.largo' => 999,
                    'web_bano_medidas.ancho' => 999,
                ],
                'web_baño' => [
                    'web_bano_medidas.largo' => 240,
                    'web_bano_medidas.ancho' => 180,
                ],
            ],
            [],
            'web_baño',
            'bano',
        );
        $start = $service->iniciar();
        $aplazado = $service->responder($start['estado'], null, [
            'web_bano_medidas.largo' => '__cliente_no_puede_decidir__',
        ]);

        $response = $service->responder($aplazado['estado'], null, [
            'web_bano_medidas.formato' => 'completo',
        ]);

        self::assertTrue($response['finalizada']);
        self::assertSame(240, $provider->generacionesRecibidas[0]['web_bano_medidas']['largo']);
        self::assertSame(180, $provider->generacionesRecibidas[0]['web_bano_medidas']['ancho']);
    }

    public function testValoresDerivadosSeSeleccionanPorConfiguradorRaiz(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBanoConMedidasYSuperficie());
        $service = new WebDuchaConversationService(
            $provider,
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [
                'web_ducha' => [
                    'web_bano_medidas.superficie' => ['expresion' => 'web_bano_medidas.largo / 10'],
                ],
                'web_baño' => [
                    'web_bano_medidas.superficie' => ['expresion' => '(web_bano_medidas.largo * web_bano_medidas.ancho) / 10000'],
                ],
            ],
            'web_baño',
            'bano',
        );

        $response = $service->responder($service->iniciar()['estado'], '240 x 180');

        self::assertTrue($response['finalizada']);
        self::assertSame(4.32, $provider->generacionesRecibidas[0]['web_bano_medidas']['superficie']);
        self::assertSame('web_baño', $response['snapshot_presupuesto']['jsonSolicitudBudgetFlow']['configurador']);
        self::assertSame('bano', $response['snapshot_presupuesto']['tipoPresupuesto']);
    }

    public function testConversacionIaSoloActivaParaConfiguradorHabilitado(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => '¿Cuál es el ancho del baño en centímetros?',
        ];
        $ai->interpretacionConversacional = [
            'valores' => [
                'web_bano_medidas.ancho' => 180,
                'precio' => 500,
            ],
            'situacion' => 'RESUELTO',
            'requiere_aclaracion' => false,
        ];
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBanoBase());
        $service = new WebDuchaConversationService(
            $provider,
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            [
                'web_ducha' => false,
                'web_baño' => true,
            ],
        );

        $start = $service->iniciar();
        $response = $service->responder($start['estado'], '180');

        self::assertSame('¿Cuál es el ancho del baño en centímetros?', $start['pregunta']);
        self::assertSame(['web_bano_medidas.ancho'], $start['estado']['campos_pendientes_actuales']);
        self::assertSame('web_baño', $ai->contextosPreguntaConversacional[0]['configurador']['codigo']);
        self::assertSame(['web_bano_medidas.ancho'], array_column($ai->contextosPreguntaConversacional[0]['campos'], 'path'));
        self::assertFalse($response['finalizada']);
        self::assertSame(180, $response['estado']['valores']['web_bano_medidas']['ancho']);
        self::assertArrayNotHasKey('precio', $response['estado']['valores']);
        self::assertSame(['web_bano_medidas.largo'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(['web_bano_medidas.largo'], array_column($ai->ultimoContextoPreguntaConversacional['campos'], 'path'));
        self::assertSame(2, $ai->preguntasConversacionales);
        self::assertSame(0, $ai->interpretacionesConversacionales);
    }

    public function testConversacionIaRechazaCamposDeConceptosNoRelacionados(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => 'Indica tipo de mampara, ancho del frente y ancho del mueble.',
        ];
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configBanoConPlatoMamparaYMueble()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['selector_plato_ducha'] = ['largo' => 140, 'ancho' => 70];
        $estado['estado_campos']['selector_plato_ducha.largo'] = 'RESUELTO';
        $estado['estado_campos']['selector_plato_ducha.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = [];

        $response = $service->responder($estado, null);

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_mampara.tipo'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(['web_mampara.tipo'], array_column($ai->ultimoContextoPreguntaConversacional['campos'], 'path'));
        self::assertSame(['web_mampara.tipo'], array_column($ai->ultimoContextoPreguntaConversacional['campos_pendientes'], 'path'));
        self::assertStringNotContainsString('mueble', mb_strtolower($response['pregunta']));
    }

    public function testConversacionIaRedactaSeleccionSoloConOpcionesExistentes(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => '¿Qué tipo de mampara prefieres: frontal o cristal fijo?',
        ];
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configBanoConPlatoMamparaYMueble()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['selector_plato_ducha'] = ['largo' => 140, 'ancho' => 70];
        $estado['estado_campos']['selector_plato_ducha.largo'] = 'RESUELTO';
        $estado['estado_campos']['selector_plato_ducha.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = [];

        $response = $service->responder($estado, null);

        self::assertSame('¿Qué tipo de mampara prefieres: frontal o cristal fijo?', $response['pregunta']);
        self::assertSame(['web_mampara.tipo'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(['frente', 'fijo'], array_column($ai->ultimoContextoPreguntaConversacional['campos'][0]['opciones'], 'codigo'));
        self::assertArrayNotHasKey('campos_solicitados', $response['estado']);
    }

    public function testConversacionIaAplicaDerivadosAntesDeSiguientePregunta(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => 'Indica tipo de mampara y ancho del frente.',
        ];
        $ai->interpretacionConversacional = [
            'valores' => [
                'web_mampara.tipo' => 'frente',
            ],
            'situacion' => 'RESUELTO',
            'requiere_aclaracion' => false,
        ];
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBanoConPlatoMamparaYMueble());
        $service = new WebDuchaConversationService(
            $provider,
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [
                'web_baño' => [
                    'web_mampara.ancho_frente' => [
                        'origen' => 'selector_plato_ducha.largo',
                        'condicion' => [
                            'campo' => 'web_mampara.tipo',
                            'valor' => 'frente',
                        ],
                    ],
                ],
            ],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['selector_plato_ducha'] = ['largo' => 140, 'ancho' => 70];
        $estado['estado_campos']['selector_plato_ducha.largo'] = 'RESUELTO';
        $estado['estado_campos']['selector_plato_ducha.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = [];
        $mampara = $service->responder($estado, null);

        self::assertSame(['web_mampara.tipo'], $mampara['estado']['campos_pendientes_actuales']);

        $response = $service->responder($mampara['estado'], 'frontal');

        self::assertFalse($response['finalizada']);
        self::assertSame(140, $response['estado']['valores']['web_mampara']['ancho_frente']);
        self::assertSame('selector_plato_ducha.largo', $response['estado']['valores_deducidos']['web_mampara']['ancho_frente']['origen']);
        self::assertSame(['web_mueble_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
        $ultimoContexto = end($ai->contextosPreguntaConversacional);
        self::assertSame(['web_mueble_bano.ancho'], array_column($ultimoContexto['campos_pendientes'], 'path'));
        self::assertSame(0, $provider->generaciones);
    }

    public function testConstantesDerivadasDeBanoEvitanPreguntarAzulejoYMetros(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBanoConRevestimientosDerivables());
        $service = new WebDuchaConversationService(
            $provider,
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [
                'web_baño' => [
                    'web_bano_medidas.altura' => ['valor' => 240],
                    'web_revestimientos.azulejo' => ['valor' => true],
                    'web_revestimientos.metros_azulejo' => [
                        'expresion' => '(((web_bano_medidas.largo + web_bano_medidas.ancho) * 2) / 100) * (web_bano_medidas.altura / 100)',
                    ],
                    'web_revestimientos.metros_suelo' => [
                        'expresion' => '(web_bano_medidas.largo / 100) * (web_bano_medidas.ancho / 100)',
                    ],
                ],
            ],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_bano_medidas'] = ['largo' => 250, 'ancho' => 320];
        $estado['estado_campos']['web_bano_medidas.largo'] = 'RESUELTO';
        $estado['estado_campos']['web_bano_medidas.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = [];

        $response = $service->responder($estado, null);

        self::assertTrue($response['finalizada']);
        self::assertSame(true, $response['estado']['valores']['web_revestimientos']['azulejo']);
        self::assertSame(27.36, $response['estado']['valores']['web_revestimientos']['metros_azulejo']);
        self::assertSame(8.0, $response['estado']['valores']['web_revestimientos']['metros_suelo']);
        self::assertSame(1, $ai->preguntasConversacionales);
    }

    public function testConfiguracionRealBanoDerivaMamparaAzulejoYSuelo(): void
    {
        $configurador = $this->definicionBanoManualConBloqueoDerivados();
        $provider = new FakeWebDuchaBudgetFlowProvider($configurador);
        $service = $this->serviceBanoIa($provider, null, $this->valoresDerivadosConfigurados());
        $estado = [
            'configurador_codigo' => 'web_baño',
            'configurador' => $configurador,
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($configurador),
            'valores' => [
                'web_medidas_bano' => [
                    'alto' => 240,
                    'largo' => 200,
                    'ancho' => 300,
                ],
                'selector_plato_ducha' => [
                    'color' => 'blanco',
                    'largo' => 180,
                    'ancho' => 90,
                ],
                'web_mueble' => [
                    'modelo' => 'noja',
                    'tipo_conjunto' => 'completo',
                    'ancho' => 900,
                    'cajones' => 3,
                    'puertas' => 0,
                    'acabado' => 'natural',
                ],
                'web_revestimientos' => [
                    'azulejo' => true,
                    'pavimento' => true,
                ],
                'web_griferia_ducha' => [
                    'tipo_griferia' => 'monomando',
                ],
            ],
            'campos_pendientes_actuales' => [],
        ];

        $response = $service->responder($estado, 'continuar');

        self::assertTrue($response['finalizada']);
        self::assertSame(180, $response['estado']['valores']['web_mampara']['ancho_frente']);
        self::assertSame(24.0, $response['estado']['valores']['web_revestimientos']['metros_azulejo']);
        self::assertSame(6.0, $response['estado']['valores']['web_revestimientos']['metros_suelo']);
        self::assertSame([
            'web_mampara' => [
                'ancho_frente' => [
                    'origen' => 'selector_plato_ducha.largo',
                    'expresion' => null,
                    'valor' => 180,
                    'regla' => 0,
                ],
            ],
            'web_revestimientos' => [
                'metros_azulejo' => [
                    'origen' => null,
                    'expresion' => '(((web_medidas_bano.largo + web_medidas_bano.ancho) * 2) / 100) * (web_medidas_bano.alto / 100)',
                    'valor' => 24.0,
                    'regla' => 0,
                ],
                'metros_suelo' => [
                    'origen' => null,
                    'expresion' => '(web_medidas_bano.largo / 100) * (web_medidas_bano.ancho / 100)',
                    'valor' => 6.0,
                    'regla' => 0,
                ],
            ],
        ], $response['estado']['valores_deducidos']);
        self::assertSame(1, $provider->generaciones);
    }

    public function testDiagnosticoGeneracionBanoPayloadRealYErrorBudgetFlow(): void
    {
        $configurador = $this->definicionBanoGeneracionReal();
        $peticiones = [];
        $logger = new DiagnosticArrayLogger();
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options = []) use (&$peticiones): MockResponse {
                $payload = json_decode($options['body'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                $peticiones[] = [
                    'method' => $method,
                    'url' => $url,
                    'payload' => $payload,
                ];

                if (str_ends_with($url, '/validar')) {
                    return new MockResponse('{"valido":true,"errores":[]}', ['http_code' => 201]);
                }

                return new MockResponse(
                    '{"title":"An error occurred","detail":"Una regla distinta de SIEMPRE debe tener un campo asociado.","status":500,"type":"\/errors\/500"}',
                    ['http_code' => 500]
                );
            },
            'https://127.0.0.1:8000'
        );
        $provider = new BudgetFlowWebBanoProvider(
            new BudgetFlowConfiguratorService(new BudgetFlowClient($httpClient, $logger)),
            $logger
        );
        $service = new WebDuchaConversationService(
            $provider,
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            $logger,
            [],
            $this->valoresDerivadosConfigurados(),
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = [
            'configurador_codigo' => 'web_baño',
            'configurador' => $configurador,
            'campos' => (new WebDuchaCampoExtractor())->extraerCampos($configurador),
            'valores' => [
                'web_medidas_bano' => [
                    'alto' => 240,
                    'largo' => 200,
                    'ancho' => 300,
                ],
                'selector_plato_ducha' => [
                    'color' => 'blanco',
                    'largo' => 150,
                    'ancho' => 60,
                ],
                'web_mueble' => [
                    'modelo' => 'noja',
                    'tipo_conjunto' => 'completo',
                    'numero_cajones' => 2,
                    'numero_puertas' => 0,
                    'acabado' => 'natural',
                    'ancho' => 800,
                ],
                'web_revestimientos' => [
                    'azulejo' => true,
                    'pavimento' => true,
                ],
            ],
            'campos_pendientes_actuales' => ['web_griferia_ducha.tipo'],
            'valores_deducidos' => [],
        ];

        $response = $service->responder($estado, null, [
            'web_griferia_ducha.tipo' => 'monomando',
        ]);

        $expectedPayload = [
            'valores' => [
                'web_medidas_bano' => [
                    'alto' => 240,
                    'largo' => 200,
                    'ancho' => 300,
                ],
                'selector_plato_ducha' => [
                    'largo' => 150,
                    'ancho' => 60,
                    'color' => 'blanco',
                ],
                'web_mueble' => [
                    'modelo' => 'noja',
                    'tipo_conjunto' => 'completo',
                    'ancho' => 800,
                    'numero_cajones' => 2,
                    'numero_puertas' => 0.0,
                    'acabado' => 'natural',
                ],
                'web_griferia_ducha' => [
                    'tipo' => 'monomando',
                ],
                'web_mampara' => [
                    'ancho_frente' => 150.0,
                ],
                'web_revestimientos' => [
                    'azulejo' => true,
                    'pavimento' => true,
                    'metros_azulejo' => 24.0,
                    'metros_suelo' => 6.0,
                ],
            ],
        ];

        self::assertFalse($response['ok']);
        self::assertSame('No hemos podido generar el presupuesto ahora mismo.', $response['error']);
        $camposUsuarioSinValor = array_values(array_filter(
            $response['estado']['campos'],
            static fn(array $campo): bool => ($campo['origenValor'] ?? null) === 'usuario'
                && !array_key_exists($campo['codigo'], $response['estado']['valores'][$campo['componente']] ?? [])
        ));

        self::assertSame([], array_column($camposUsuarioSinValor, 'id'));
        self::assertSame(['web_griferia_ducha.tipo'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame('monomando', $response['estado']['valores']['web_griferia_ducha']['tipo']);
        self::assertSame(150.0, $response['estado']['valores']['web_mampara']['ancho_frente']);
        self::assertSame(24.0, $response['estado']['valores']['web_revestimientos']['metros_azulejo']);
        self::assertSame(6.0, $response['estado']['valores']['web_revestimientos']['metros_suelo']);
        self::assertSame([
            'web_mampara' => [
                'ancho_frente' => [
                    'origen' => 'selector_plato_ducha.largo',
                    'expresion' => null,
                    'valor' => 150.0,
                    'regla' => 0,
                ],
            ],
            'web_revestimientos' => [
                'metros_azulejo' => [
                    'origen' => null,
                    'expresion' => '(((web_medidas_bano.largo + web_medidas_bano.ancho) * 2) / 100) * (web_medidas_bano.alto / 100)',
                    'valor' => 24.0,
                    'regla' => 0,
                ],
                'metros_suelo' => [
                    'origen' => null,
                    'expresion' => '(web_medidas_bano.largo / 100) * (web_medidas_bano.ancho / 100)',
                    'valor' => 6.0,
                    'regla' => 0,
                ],
            ],
        ], $response['estado']['valores_deducidos']);
        self::assertSame('POST', $peticiones[0]['method']);
        self::assertStringEndsWith('/api/v1/configuradores/web_ba%C3%B1o/validar', $peticiones[0]['url']);
        self::assertSame($expectedPayload, $peticiones[0]['payload']);
        self::assertSame('POST', $peticiones[1]['method']);
        self::assertStringEndsWith('/api/v1/configuradores/web_ba%C3%B1o/generar', $peticiones[1]['url']);
        self::assertSame($expectedPayload, $peticiones[1]['payload']);
        $validacionResponse = $this->registroLog($logger->records, 'web_presupuesto_ducha.budgetflow.validar.response');
        $errorHttp = $this->registroLog($logger->records, 'BudgetFlow respondió con error HTTP.');
        $errorGeneracion = $this->registroLog($logger->records, 'BudgetFlow no disponible al generar presupuesto web ducha.');

        self::assertSame(['valido' => true, 'errores' => []], $validacionResponse['context']['response']);
        self::assertSame(500, $errorHttp['context']['status']);
        self::assertStringContainsString('Una regla distinta de SIEMPRE debe tener un campo asociado.', $errorHttp['context']['response_body']);
        self::assertSame('BudgetFlow no disponible al generar presupuesto web ducha.', $errorGeneracion['message']);
    }

    public function testConversacionIaCampoEnteroUnicoAceptaNumeroDirecto(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase());
        $service = new WebDuchaConversationService(
            $provider,
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['ancho'] = 180;
        $estado['estado_campos']['web_medidas_bano.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = ['web_medidas_bano.largo'];

        $response = $service->responder($estado, '240');

        self::assertTrue($response['finalizada']);
        self::assertSame(240, $response['estado']['valores']['web_medidas_bano']['largo']);
        self::assertSame(180, $response['estado']['valores']['web_medidas_bano']['ancho']);
        self::assertSame([], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(0, $ai->interpretacionesConversacionales);
    }

    public function testConversacionIaCampoDecimalUnicoAceptaComaDecimal(): void
    {
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configCampoDecimalBano()),
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '2,5');

        self::assertTrue($response['finalizada']);
        self::assertSame(2.5, $response['estado']['valores']['web_medidas_bano']['superficie']);
    }

    public function testConversacionIaRespuestaNaturalUsaInterpretacionIaDelCampoActual(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->interpretacionConversacional = [
            'valores' => [
                'web_medidas_bano.largo' => 240,
            ],
            'situacion' => 'RESUELTO',
            'requiere_aclaracion' => false,
        ];
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['ancho'] = 180;
        $estado['estado_campos']['web_medidas_bano.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = ['web_medidas_bano.largo'];

        $response = $service->responder($estado, 'dos cuarenta');

        self::assertTrue($response['finalizada']);
        self::assertSame(240, $response['estado']['valores']['web_medidas_bano']['largo']);
        self::assertSame(180, $response['estado']['valores']['web_medidas_bano']['ancho']);
        self::assertSame(1, $ai->interpretacionesConversacionales);
    }

    public function testConversacionIaMapeaPathDevueltoPorIaAEstadoAnidado(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->interpretacionConversacional = [
            'valores' => [
                'web_medidas_bano.largo' => 240,
            ],
            'situacion' => 'RESUELTO',
            'requiere_aclaracion' => false,
        ];
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['ancho'] = 180;
        $estado['estado_campos']['web_medidas_bano.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = ['web_medidas_bano.largo'];

        $response = $service->responder($estado, 'la medida que comentamos');

        self::assertTrue($response['finalizada']);
        self::assertSame(240, $response['estado']['valores']['web_medidas_bano']['largo']);
        self::assertArrayNotHasKey('web_medidas_bano.largo', $response['estado']['valores']);
        self::assertSame(1, $ai->interpretacionesConversacionales);
    }

    public function testFallbackDeMedidasConservaEtiquetasCompletas(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->fallarPregunta = true;
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );

        $response = $service->iniciar();

        self::assertStringContainsString('largo del baño', $response['pregunta']);
        self::assertStringNotContainsString('Ancho del baño', $response['pregunta']);
        self::assertNotSame('Indica largo y ancho para continuar.', $response['pregunta']);
    }

    public function testConversacionIaNumeroDirectoCuentaComoProgreso(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['ancho'] = 180;
        $estado['estado_campos']['web_medidas_bano.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = ['web_medidas_bano.largo'];

        $response = $service->responder($estado, '240');

        self::assertTrue($response['finalizada']);
        self::assertSame('RESUELTO', $response['estado']['ultima_situacion']);
        self::assertSame(0, $response['estado']['intentos_sin_progreso']['web_medidas_bano.largo']);
        self::assertArrayNotHasKey('pregunta', $response);
    }

    public function testConversacionIaFalloInterpretacionNoAplazaCampo(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->interpretacionConversacional = [
            'valores' => [],
            'situacion' => 'AMBIGUA',
            'requiere_aclaracion' => false,
        ];
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase()),
            $ai,
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['ancho'] = 180;
        $estado['estado_campos']['web_medidas_bano.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = ['web_medidas_bano.largo'];

        $response = $service->responder($estado, '240 x 300');

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_medidas_bano.largo'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame('PENDIENTE', $response['estado']['estado_campos']['web_medidas_bano.largo']);
        self::assertSame([], $response['estado']['campos_aplazados']);
        self::assertStringContainsString('No he podido interpretar', $response['pregunta']);
    }

    public function testConversacionIaNoLoSeExplicitoAplazaCampo(): void
    {
        $service = new WebDuchaConversationService(
            new FakeWebDuchaBudgetFlowProvider($this->configMedidasBanoBase()),
            new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [
                'web_baño' => [
                    'web_medidas_bano.largo' => 240,
                ],
            ],
            [],
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['ancho'] = 180;
        $estado['estado_campos']['web_medidas_bano.ancho'] = 'RESUELTO';
        $estado['campos_pendientes_actuales'] = ['web_medidas_bano.largo'];

        $response = $service->responder($estado, 'no lo sé');

        self::assertTrue($response['finalizada']);
        self::assertSame('APLAZADO_POR_CLIENTE', $response['estado']['estado_campos']['web_medidas_bano.largo']);
        self::assertSame(['web_medidas_bano.largo'], $response['estado']['campos_aplazados']);
        self::assertTrue($response['contiene_estimaciones']);
    }

    public function testDefinicionSeleccionaPrimerBloqueMedidasBanoYAplicaFijo(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $provider = new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional());
        $service = $this->serviceBanoIa($provider, $ai);

        $response = $service->iniciar();

        self::assertTrue($response['ok']);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(240, $response['estado']['valores']['web_medidas_bano']['alto']);
        self::assertNotContains('web_medidas_bano.alto', $response['estado']['campos_pendientes_actuales']);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], array_column($ai->ultimoContextoPreguntaConversacional['campos'], 'path'));
        self::assertSame('cm', $ai->ultimoContextoPreguntaConversacional['campos'][0]['unidad']);
        self::assertSame('200', $ai->ultimoContextoPreguntaConversacional['campos'][0]['valorEjemplo']);
    }

    public function testDefinicionStartUsaRedactorIaParaMedidasBano(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => '¿Cuánto mide aproximadamente el baño? Puedes indicarme largo x ancho en centímetros, por ejemplo 200 x 250.',
        ];
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);

        $response = $service->iniciar();
        $camposRedactor = $ai->ultimoContextoPreguntaConversacional['campos'];

        self::assertSame('¿Cuánto mide aproximadamente el baño? Puedes indicarme largo x ancho en centímetros, por ejemplo 200 x 250.', $response['pregunta']);
        self::assertSame(1, $ai->preguntasConversacionales);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], array_column($camposRedactor, 'path'));
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], array_column($ai->ultimoContextoPreguntaConversacional['campos_pendientes'], 'path'));
        self::assertSame(['Largo', 'Ancho'], array_column($camposRedactor, 'etiqueta'));
        self::assertSame(['La medida de un lateral del baño en cm', 'Medida del ancho del baño en centímetros'], array_column($camposRedactor, 'ayuda'));
        self::assertSame(['entero', 'entero'], array_column($camposRedactor, 'tipo'));
        self::assertSame(['cm', 'cm'], array_column($camposRedactor, 'unidad'));
        self::assertSame(['200', '250'], array_column($camposRedactor, 'valorEjemplo'));
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_solicitados_actuales']);
    }

    public function testDefinicionRedactorNoPuedeAmpliarCamposDelTurno(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => '¿Cuánto mide el baño y qué ancho del mueble quieres?',
        ];
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);

        $response = $service->iniciar();

        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
        self::assertStringNotContainsString('mueble', mb_strtolower($response['pregunta']));
    }

    public function testDefinicionRedactorNoAceptaPreguntaParcialDelGrupo(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => '¿Cuál es el largo del baño en cm?',
        ];
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);

        $response = $service->iniciar();

        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_solicitados_actuales']);
        self::assertNotSame('¿Cuál es el largo del baño en cm?', $response['pregunta']);
    }

    public function testDefinicionRedactorFallbackSiFalla(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->fallarPregunta = true;
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);

        $response = $service->iniciar();

        self::assertSame('Necesito estos datos para continuar: Largo y Ancho.', $response['pregunta']);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testDefinicionRedactorFallbackSiDevuelveVacio(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => '',
        ];
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);

        $response = $service->iniciar();

        self::assertSame('Necesito estos datos para continuar: Largo y Ancho.', $response['pregunta']);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testDefinicionAplicaDefaultsSoloFijoEInternoEnEstadoInicial(): void
    {
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()));

        $response = $service->iniciar();
        $valores = $response['estado']['valores'];

        self::assertSame(240, $valores['web_medidas_bano']['alto']);
        self::assertSame('blanco', $valores['selector_plato_ducha']['color']);
        self::assertSame('noja', $valores['web_mueble']['modelo']);
        self::assertSame('completo', $valores['web_mueble']['tipo_conjunto']);
        self::assertTrue($valores['web_revestimientos']['azulejo']);
        self::assertTrue($valores['web_revestimientos']['pavimento']);

        self::assertArrayNotHasKey('largo', $valores['web_medidas_bano']);
        self::assertArrayNotHasKey('ancho', $valores['web_medidas_bano']);
        self::assertArrayNotHasKey('ancho_frente', $valores['web_mampara'] ?? []);
        self::assertArrayNotHasKey('metros_suelo', $valores['web_revestimientos']);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testDefinicionInterpretaRespuestaAgrupadaYAgrupaPlatoDespues(): void
    {
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '200 x 300');

        self::assertFalse($response['finalizada']);
        self::assertSame(200, $response['estado']['valores']['web_medidas_bano']['largo']);
        self::assertSame(300, $response['estado']['valores']['web_medidas_bano']['ancho']);
        self::assertSame(['selector_plato_ducha.largo', 'selector_plato_ducha.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    /**
     * @dataProvider respuestasMedidasAgrupadasProvider
     */
    public function testDefinicionInterpretaFormatosAgrupadosDeDosNumeros(string $respuesta): void
    {
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], $respuesta);

        self::assertSame(200, $response['estado']['valores']['web_medidas_bano']['largo']);
        self::assertSame(300, $response['estado']['valores']['web_medidas_bano']['ancho']);
        self::assertNotContains('web_medidas_bano.largo', $response['estado']['campos_pendientes_actuales']);
        self::assertNotContains('web_medidas_bano.ancho', $response['estado']['campos_pendientes_actuales']);
    }

    public function respuestasMedidasAgrupadasProvider(): array
    {
        return [
            ['200x300'],
            ['200 X 300'],
            ['200 por 300'],
        ];
    }

    public function testDefinicionRespuestaParcialResuelvePrimerCampoYPreguntaSoloElPendiente(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], '200');

        self::assertSame(200, $response['estado']['valores']['web_medidas_bano']['largo']);
        self::assertArrayNotHasKey('ancho', $response['estado']['valores']['web_medidas_bano']);
        self::assertSame(['web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame(['web_medidas_bano.ancho'], array_column($ai->ultimoContextoPreguntaConversacional['campos'], 'path'));
    }

    public function testDefinicionNoPreguntaInternosNiDerivadosYNoMezclaGrupos(): void
    {
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()));
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['largo'] = 200;
        $estado['valores']['web_medidas_bano']['ancho'] = 300;
        $estado['valores']['selector_plato_ducha']['largo'] = 120;
        $estado['valores']['selector_plato_ducha']['ancho'] = 70;
        $estado['campos_pendientes_actuales'] = [];

        $response = $service->responder($estado, null);

        self::assertSame('blanco', $response['estado']['valores']['selector_plato_ducha']['color']);
        self::assertSame(['web_mampara.tipo'], $response['estado']['campos_pendientes_actuales']);
        self::assertNotContains('selector_plato_ducha.color', $response['estado']['campos_pendientes_actuales']);
        self::assertNotContains('web_mampara.ancho_frente', $response['estado']['campos_pendientes_actuales']);
    }

    public function testDefinicionNoRepiteCampoDeGrupoYaResuelto(): void
    {
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()));
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['largo'] = 200;
        $estado['campos_pendientes_actuales'] = [];

        $response = $service->responder($estado, null);

        self::assertSame(['web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testDefinicionEnviaOpcionesAlRedactorIaYLaIaNoAmpliaCampos(): void
    {
        $ai = new FakeWebPresupuestoAiClient();
        $ai->preguntaConversacional = [
            'pregunta' => 'Indica tipo de mampara y color del plato.',
        ];
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()), $ai);
        $estado = $service->iniciar()['estado'];
        $estado['valores']['web_medidas_bano']['largo'] = 200;
        $estado['valores']['web_medidas_bano']['ancho'] = 300;
        $estado['valores']['selector_plato_ducha']['largo'] = 120;
        $estado['valores']['selector_plato_ducha']['ancho'] = 70;
        $estado['campos_pendientes_actuales'] = [];

        $response = $service->responder($estado, null);

        self::assertSame(['frente', 'fijo'], array_column($ai->ultimoContextoPreguntaConversacional['campos'][0]['opciones'], 'codigo'));
        self::assertSame(['web_mampara.tipo'], $response['estado']['campos_pendientes_actuales']);
        self::assertStringNotContainsString('color', mb_strtolower($response['pregunta']));
    }

    public function testDefinicionFalloInterpretacionMantieneMismoTurno(): void
    {
        $service = $this->serviceBanoIa(new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()));
        $start = $service->iniciar();

        $response = $service->responder($start['estado'], 'medidas normales');

        self::assertFalse($response['finalizada']);
        self::assertSame(['web_medidas_bano.largo', 'web_medidas_bano.ancho'], $response['estado']['campos_pendientes_actuales']);
        self::assertSame([], $response['estado']['campos_aplazados']);
    }

    public function testFalloDefinicionUsaFallbackConfiguradorActual(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->configBanoBase());
        $provider->fallarDefinicion = true;
        $service = $this->serviceBanoIa($provider);

        $response = $service->iniciar();

        self::assertTrue($response['ok']);
        self::assertSame(['web_bano_medidas.ancho'], $response['estado']['campos_pendientes_actuales']);
    }

    public function testDiagnosticoBanoManualQuedaBloqueadoPorDerivadosSinCampoUsuario(): void
    {
        $provider = new FakeWebDuchaBudgetFlowProvider($this->definicionBanoManualConBloqueoDerivados());
        $service = $this->serviceBanoIa($provider);

        $response = $service->iniciar();
        $response = $service->responder($response['estado'], '200 x 330');
        $response = $service->responder($response['estado'], '150x80');
        $response = $service->responder($response['estado'], '900');
        $response = $service->responder($response['estado'], '3');
        $response = $service->responder($response['estado'], '0');
        $response = $service->responder($response['estado'], 'natural');
        $response = $service->responder($response['estado'], 'Monomando');

        $estado = $response['estado'];
        $camposSinValor = array_values(array_filter(
            $estado['campos'],
            static fn(array $campo): bool => !array_key_exists($campo['codigo'], $estado['valores'][$campo['componente']] ?? [])
        ));
        $diagnosticoPendientes = array_map(static fn(array $campo): array => [
            'path' => $campo['id'],
            'origenValor' => $campo['origenValor'] ?? null,
            'obligatorio' => $campo['obligatorio'] ?? null,
            'valor' => $estado['valores'][$campo['componente']][$campo['codigo']] ?? null,
            'motivo' => ($campo['origenValor'] ?? null) === 'usuario' ? 'preguntable' : 'no_preguntable_por_origen',
        ], $camposSinValor);
        $erroresValidacion = array_map(
            static fn(array $error): string => $error['configurador'].'.'.$error['campo'],
            $estado['validacion']['errores']
        );

        self::assertFalse($response['finalizada']);
        self::assertSame('Necesito un dato más para continuar.', $response['pregunta']);
        self::assertSame([], $response['campos']);
        self::assertSame([], $estado['campos_pendientes_actuales']);
        self::assertSame([], $estado['campos_solicitados_actuales']);
        self::assertSame([], $estado['valores_deducidos']);

        self::assertSame([
            'web_medidas_bano' => [
                'alto' => 240,
                'largo' => 200,
                'ancho' => 330,
            ],
            'selector_plato_ducha' => [
                'color' => 'blanco',
                'largo' => 150,
                'ancho' => 80,
            ],
            'web_mueble' => [
                'modelo' => 'noja',
                'tipo_conjunto' => 'completo',
                'ancho' => 900,
                'cajones' => 3,
                'puertas' => 0,
                'acabado' => 'natural',
            ],
            'web_revestimientos' => [
                'azulejo' => true,
                'pavimento' => true,
            ],
            'web_griferia_ducha' => [
                'tipo_griferia' => 'monomando',
            ],
        ], $estado['valores']);

        self::assertSame([
            [
                'path' => 'web_mampara.ancho_frente',
                'origenValor' => 'derivado',
                'obligatorio' => true,
                'valor' => null,
                'motivo' => 'no_preguntable_por_origen',
            ],
            [
                'path' => 'web_revestimientos.metros_azulejo',
                'origenValor' => 'derivado',
                'obligatorio' => true,
                'valor' => null,
                'motivo' => 'no_preguntable_por_origen',
            ],
            [
                'path' => 'web_revestimientos.metros_suelo',
                'origenValor' => 'derivado',
                'obligatorio' => true,
                'valor' => null,
                'motivo' => 'no_preguntable_por_origen',
            ],
        ], $diagnosticoPendientes);
        self::assertSame([], array_values(array_filter(
            $diagnosticoPendientes,
            static fn(array $campo): bool => $campo['origenValor'] === 'usuario'
        )));
        self::assertSame([
            'web_mampara.ancho_frente',
            'web_revestimientos.metros_azulejo',
            'web_revestimientos.metros_suelo',
        ], $erroresValidacion);
        self::assertSame([
            'web_mampara.ancho_frente',
            'web_revestimientos.metros_azulejo',
            'web_revestimientos.metros_suelo',
        ], array_map(
            static fn(array $error): string => $error['configurador'].'.'.$error['campo'],
            $response['errores']
        ));
        self::assertFalse($estado['validacion']['valido']);
        self::assertSame(0, $provider->generaciones);
    }

    private function service(
        ?FakeWebDuchaBudgetFlowProvider $provider = null,
        ?FakeWebPresupuestoAiClient $ai = null,
        array $valoresProvisionales = [],
        array $valoresDerivados = []
    ): WebDuchaConversationService {
        return new WebDuchaConversationService(
            $provider ?? new FakeWebDuchaBudgetFlowProvider($this->configBase()),
            $ai ?? new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            $valoresProvisionales,
            $valoresDerivados,
        );
    }

    private function serviceBanoIa(
        ?FakeWebDuchaBudgetFlowProvider $provider = null,
        ?FakeWebPresupuestoAiClient $ai = null,
        array $valoresDerivados = []
    ): WebDuchaConversationService {
        return new WebDuchaConversationService(
            $provider ?? new FakeWebDuchaBudgetFlowProvider($this->definicionBanoConversacional()),
            $ai ?? new FakeWebPresupuestoAiClient(),
            new WebDuchaCampoExtractor(),
            new NullLogger(),
            [],
            $valoresDerivados,
            'web_baño',
            'bano',
            ['web_baño' => true],
        );
    }

    private function valoresDerivadosConfigurados(): array
    {
        $config = Yaml::parseFile(__DIR__.'/../../config/services.yaml');

        return $config['parameters']['web_presupuesto.valores_derivados'];
    }

    private function registroLog(array $records, string $message): array
    {
        foreach ($records as $record) {
            if (($record['message'] ?? null) === $message) {
                return $record;
            }
        }

        self::fail(sprintf('No se encontró el log "%s".', $message));
    }

    private function definicionBanoConversacional(): array
    {
        return [
            '__usar_como_definicion' => true,
            'codigo' => 'web_baño',
            'nombre' => 'Baño completo desde Web',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'web_mampara',
                    'orden' => 5,
                    'campos' => [
                        [
                            'path' => 'web_mampara.tipo',
                            'codigo' => 'tipo',
                            'etiqueta' => 'Tipo de mampara',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'usuario',
                            'opciones' => [
                                ['codigo' => 'frente', 'etiqueta' => 'Frontal'],
                                ['codigo' => 'fijo', 'etiqueta' => 'Cristal fijo'],
                            ],
                        ],
                        [
                            'path' => 'web_mampara.ancho_frente',
                            'codigo' => 'ancho_frente',
                            'etiqueta' => 'Ancho del frente',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'derivado',
                            'valorDefecto' => 150,
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_medidas_bano',
                    'orden' => 1,
                    'campos' => [
                        [
                            'path' => 'web_medidas_bano.alto',
                            'codigo' => 'alto',
                            'etiqueta' => 'Alto',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'fijo',
                            'valorDefecto' => 240,
                            'unidad' => 'cm',
                        ],
                        [
                            'path' => 'web_medidas_bano.largo',
                            'codigo' => 'largo',
                            'etiqueta' => 'Largo',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_bano',
                            'unidad' => 'cm',
                            'ayuda' => 'La medida de un lateral del baño en cm',
                            'valorDefecto' => 200,
                            'valorEjemplo' => '200',
                        ],
                        [
                            'path' => 'web_medidas_bano.ancho',
                            'codigo' => 'ancho',
                            'etiqueta' => 'Ancho',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_bano',
                            'unidad' => 'cm',
                            'ayuda' => 'Medida del ancho del baño en centímetros',
                            'valorDefecto' => 250,
                            'valorEjemplo' => '250',
                        ],
                    ],
                ],
                [
                    'codigo' => 'selector_plato_ducha',
                    'orden' => 4,
                    'campos' => [
                        [
                            'path' => 'selector_plato_ducha.largo',
                            'codigo' => 'largo',
                            'etiqueta' => 'Largo del plato',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_plato',
                            'unidad' => 'cm',
                        ],
                        [
                            'path' => 'selector_plato_ducha.ancho',
                            'codigo' => 'ancho',
                            'etiqueta' => 'Ancho del plato',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_plato',
                            'unidad' => 'cm',
                        ],
                        [
                            'path' => 'selector_plato_ducha.color',
                            'codigo' => 'color',
                            'etiqueta' => 'Color',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'interno',
                            'valorDefecto' => 'blanco',
                            'opciones' => [
                                ['codigo' => 'blanco', 'etiqueta' => 'Blanco'],
                            ],
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_mueble',
                    'orden' => 5,
                    'campos' => [
                        [
                            'path' => 'web_mueble.modelo',
                            'codigo' => 'modelo',
                            'etiqueta' => 'Modelo',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'interno',
                            'valorDefecto' => 'noja',
                        ],
                        [
                            'path' => 'web_mueble.tipo_conjunto',
                            'codigo' => 'tipo_conjunto',
                            'etiqueta' => 'Tipo de conjunto',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'interno',
                            'valorDefecto' => 'completo',
                        ],
                        [
                            'path' => 'web_mueble.ancho',
                            'codigo' => 'ancho',
                            'etiqueta' => 'Ancho del mueble',
                            'tipo' => 'entero',
                            'obligatorio' => false,
                            'orden' => 3,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_mueble',
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_revestimientos',
                    'orden' => 10,
                    'campos' => [
                        [
                            'path' => 'web_revestimientos.azulejo',
                            'codigo' => 'azulejo',
                            'etiqueta' => 'Azulejo',
                            'tipo' => 'booleano',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'fijo',
                            'valorDefecto' => true,
                        ],
                        [
                            'path' => 'web_revestimientos.pavimento',
                            'codigo' => 'pavimento',
                            'etiqueta' => 'Pavimento',
                            'tipo' => 'booleano',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'fijo',
                            'valorDefecto' => true,
                        ],
                        [
                            'path' => 'web_revestimientos.metros_suelo',
                            'codigo' => 'metros_suelo',
                            'etiqueta' => 'Metros de suelo',
                            'tipo' => 'decimal',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'derivado',
                            'valorDefecto' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function definicionBanoManualConBloqueoDerivados(): array
    {
        return [
            '__usar_como_definicion' => true,
            'codigo' => 'web_baño',
            'nombre' => 'Baño completo desde Web',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'web_medidas_bano',
                    'orden' => 1,
                    'campos' => [
                        [
                            'path' => 'web_medidas_bano.alto',
                            'codigo' => 'alto',
                            'etiqueta' => 'Alto',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'fijo',
                            'valorDefecto' => 240,
                            'unidad' => 'cm',
                        ],
                        [
                            'path' => 'web_medidas_bano.largo',
                            'codigo' => 'largo',
                            'etiqueta' => 'Largo',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_bano',
                            'unidad' => 'cm',
                            'valorEjemplo' => '200',
                        ],
                        [
                            'path' => 'web_medidas_bano.ancho',
                            'codigo' => 'ancho',
                            'etiqueta' => 'Ancho',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_bano',
                            'unidad' => 'cm',
                            'valorEjemplo' => '330',
                        ],
                    ],
                ],
                [
                    'codigo' => 'selector_plato_ducha',
                    'orden' => 2,
                    'campos' => [
                        [
                            'path' => 'selector_plato_ducha.largo',
                            'codigo' => 'largo',
                            'etiqueta' => 'Largo del plato',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_plato',
                            'unidad' => 'cm',
                        ],
                        [
                            'path' => 'selector_plato_ducha.ancho',
                            'codigo' => 'ancho',
                            'etiqueta' => 'Ancho del plato',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'usuario',
                            'grupoEntrada' => 'medidas_plato',
                            'unidad' => 'cm',
                        ],
                        [
                            'path' => 'selector_plato_ducha.color',
                            'codigo' => 'color',
                            'etiqueta' => 'Color',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'interno',
                            'valorDefecto' => 'blanco',
                            'opciones' => [
                                ['codigo' => 'blanco', 'etiqueta' => 'Blanco'],
                            ],
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_mueble',
                    'orden' => 3,
                    'campos' => [
                        [
                            'path' => 'web_mueble.modelo',
                            'codigo' => 'modelo',
                            'etiqueta' => 'Modelo',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'interno',
                            'valorDefecto' => 'noja',
                        ],
                        [
                            'path' => 'web_mueble.tipo_conjunto',
                            'codigo' => 'tipo_conjunto',
                            'etiqueta' => 'Tipo de conjunto',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'interno',
                            'valorDefecto' => 'completo',
                        ],
                        [
                            'path' => 'web_mueble.ancho',
                            'codigo' => 'ancho',
                            'etiqueta' => 'Ancho del mueble',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'usuario',
                            'unidad' => 'mm',
                            'valorEjemplo' => '900',
                        ],
                        [
                            'path' => 'web_mueble.cajones',
                            'codigo' => 'cajones',
                            'etiqueta' => 'Cajones',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 4,
                            'origenValor' => 'usuario',
                            'valorEjemplo' => '3',
                        ],
                        [
                            'path' => 'web_mueble.puertas',
                            'codigo' => 'puertas',
                            'etiqueta' => 'Puertas',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 5,
                            'origenValor' => 'usuario',
                            'valorEjemplo' => '0',
                        ],
                        [
                            'path' => 'web_mueble.acabado',
                            'codigo' => 'acabado',
                            'etiqueta' => 'Acabado',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 6,
                            'origenValor' => 'usuario',
                            'opciones' => [
                                ['codigo' => 'natural', 'etiqueta' => 'Natural'],
                                ['codigo' => 'blanco', 'etiqueta' => 'Blanco'],
                            ],
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_griferia_ducha',
                    'orden' => 4,
                    'campos' => [
                        [
                            'path' => 'web_griferia_ducha.tipo_griferia',
                            'codigo' => 'tipo_griferia',
                            'etiqueta' => 'Tipo de griferia',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'usuario',
                            'opciones' => [
                                ['codigo' => 'monomando', 'etiqueta' => 'Monomando'],
                                ['codigo' => 'termostatica', 'etiqueta' => 'Termostatica'],
                            ],
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_mampara',
                    'orden' => 5,
                    'campos' => [
                        [
                            'path' => 'web_mampara.ancho_frente',
                            'codigo' => 'ancho_frente',
                            'etiqueta' => 'Ancho del frente',
                            'tipo' => 'entero',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'derivado',
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_revestimientos',
                    'orden' => 10,
                    'campos' => [
                        [
                            'path' => 'web_revestimientos.azulejo',
                            'codigo' => 'azulejo',
                            'etiqueta' => 'Azulejo',
                            'tipo' => 'booleano',
                            'obligatorio' => true,
                            'orden' => 1,
                            'origenValor' => 'fijo',
                            'valorDefecto' => true,
                        ],
                        [
                            'path' => 'web_revestimientos.pavimento',
                            'codigo' => 'pavimento',
                            'etiqueta' => 'Pavimento',
                            'tipo' => 'booleano',
                            'obligatorio' => true,
                            'orden' => 2,
                            'origenValor' => 'fijo',
                            'valorDefecto' => true,
                        ],
                        [
                            'path' => 'web_revestimientos.metros_azulejo',
                            'codigo' => 'metros_azulejo',
                            'etiqueta' => 'Metros de azulejo',
                            'tipo' => 'decimal',
                            'obligatorio' => true,
                            'orden' => 3,
                            'origenValor' => 'derivado',
                        ],
                        [
                            'path' => 'web_revestimientos.metros_suelo',
                            'codigo' => 'metros_suelo',
                            'etiqueta' => 'Metros de suelo',
                            'tipo' => 'decimal',
                            'obligatorio' => true,
                            'orden' => 4,
                            'origenValor' => 'derivado',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function definicionBanoGeneracionReal(): array
    {
        $definicion = $this->definicionBanoManualConBloqueoDerivados();

        foreach ($definicion['componentes'] as &$componente) {
            foreach ($componente['campos'] as &$campo) {
                if (($campo['path'] ?? null) === 'web_mueble.cajones') {
                    $campo['path'] = 'web_mueble.numero_cajones';
                    $campo['codigo'] = 'numero_cajones';
                    $campo['etiqueta'] = 'Numero de cajones';
                    continue;
                }

                if (($campo['path'] ?? null) === 'web_mueble.puertas') {
                    $campo['path'] = 'web_mueble.numero_puertas';
                    $campo['codigo'] = 'numero_puertas';
                    $campo['etiqueta'] = 'Numero de puertas';
                    $campo['tipo'] = 'decimal';
                    continue;
                }

                if (($campo['path'] ?? null) === 'web_griferia_ducha.tipo_griferia') {
                    $campo['path'] = 'web_griferia_ducha.tipo';
                    $campo['codigo'] = 'tipo';
                    continue;
                }

                if (($campo['path'] ?? null) === 'web_mampara.ancho_frente') {
                    $campo['tipo'] = 'decimal';
                }
            }
        }
        unset($campo, $componente);

        return $definicion;
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

    private function configBaseConRangos(int|float|null $minimo, int|float|null $maximo): array
    {
        $config = $this->configBase();

        foreach ($config['componentes'][0]['configurador']['campos'] as &$campo) {
            if (!in_array($campo['codigo'] ?? null, ['largo', 'ancho'], true)) {
                continue;
            }

            $campo['grupoEntrada'] = 'medidas_plato';
            $campo['orden'] = $campo['codigo'] === 'largo' ? 1 : 2;
            $campo['valorMinimo'] = $minimo;
            $campo['valorMaximo'] = $maximo;
            $campo['valorEjemplo'] = $campo['codigo'] === 'largo' ? 160 : 70;
        }
        unset($campo);

        return $config;
    }

    private function configBanoBase(): array
    {
        return [
            'codigo' => 'web_baño',
            'nombre' => 'Baño completo desde Web',
            'tipo' => 'compuesto',
            'version' => 1,
            'campos' => [],
            'componentes' => [
                [
                    'codigo' => 'web_bano_medidas',
                    'orden' => 1,
                    'obligatorio' => true,
                    'configurador' => [
                        'codigo' => 'web_bano_medidas',
                        'tipo' => 'simple',
                        'campos' => [
                            [
                                'codigo' => 'ancho',
                                'etiqueta' => 'Ancho del baño',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 1,
                                'ayuda' => 'Ancho del baño expresado en centimetros.',
                                'opciones' => [],
                            ],
                            [
                                'codigo' => 'largo',
                                'etiqueta' => 'Largo del baño',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 2,
                                'ayuda' => 'Largo del baño expresado en centimetros.',
                                'opciones' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function configBanoConMedidasYSeleccion(): array
    {
        $config = $this->configBanoBase();
        $config['componentes'][0]['configurador']['campos'][] = [
            'codigo' => 'formato',
            'etiqueta' => 'Formato de reforma',
            'tipo' => 'seleccion',
            'obligatorio' => true,
            'orden' => 3,
            'opciones' => [
                ['codigo' => 'completo', 'etiqueta' => 'Baño completo'],
            ],
        ];

        return $config;
    }

    private function configBanoConMedidasYSuperficie(): array
    {
        $config = $this->configBanoBase();
        $config['componentes'][0]['configurador']['campos'][] = [
            'codigo' => 'superficie',
            'etiqueta' => 'Superficie del baño',
            'tipo' => 'decimal',
            'obligatorio' => true,
            'orden' => 3,
            'opciones' => [],
        ];

        return $config;
    }

    private function configMedidasBanoBase(): array
    {
        return [
            'codigo' => 'web_baño',
            'nombre' => 'Baño completo desde Web',
            'tipo' => 'compuesto',
            'version' => 1,
            'campos' => [],
            'componentes' => [
                [
                    'codigo' => 'web_medidas_bano',
                    'orden' => 1,
                    'obligatorio' => true,
                    'configurador' => [
                        'codigo' => 'web_medidas_bano',
                        'tipo' => 'simple',
                        'campos' => [
                            [
                                'codigo' => 'largo',
                                'etiqueta' => 'Largo del baño',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 1,
                                'ayuda' => 'Quiero la medida en centimetros.',
                                'opciones' => [],
                            ],
                            [
                                'codigo' => 'ancho',
                                'etiqueta' => 'Ancho del baño',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 2,
                                'ayuda' => 'Quiero la medida en centimetros.',
                                'opciones' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function configCampoDecimalBano(int|float|null $minimo = null, int|float|null $maximo = null): array
    {
        $config = $this->configMedidasBanoBase();
        $config['componentes'][0]['configurador']['campos'] = [
            [
                'codigo' => 'superficie',
                'etiqueta' => 'Superficie del baño',
                'tipo' => 'decimal',
                'obligatorio' => true,
                'orden' => 1,
                'opciones' => [],
                'valorMinimo' => $minimo,
                'valorMaximo' => $maximo,
            ],
        ];

        return $config;
    }

    private function configBanoConPlatoMamparaYMueble(): array
    {
        return [
            'codigo' => 'web_baño',
            'nombre' => 'Baño completo desde Web',
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
                                'codigo' => 'largo',
                                'etiqueta' => 'Largo del plato',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 1,
                                'ayuda' => 'Largo del plato expresado en centimetros.',
                                'opciones' => [],
                            ],
                            [
                                'codigo' => 'ancho',
                                'etiqueta' => 'Ancho del plato',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 2,
                                'ayuda' => 'Ancho del plato expresado en centimetros.',
                                'opciones' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_mampara',
                    'orden' => 2,
                    'obligatorio' => true,
                    'configurador' => [
                        'codigo' => 'web_mampara',
                        'tipo' => 'simple',
                        'campos' => [
                            [
                                'codigo' => 'tipo',
                                'etiqueta' => 'Tipo de mampara',
                                'tipo' => 'seleccion',
                                'obligatorio' => true,
                                'orden' => 1,
                                'opciones' => [
                                    ['codigo' => 'frente', 'etiqueta' => 'Frontal'],
                                    ['codigo' => 'fijo', 'etiqueta' => 'Cristal fijo'],
                                ],
                            ],
                            [
                                'codigo' => 'ancho_frente',
                                'etiqueta' => 'Ancho del frente',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 2,
                                'ayuda' => 'Medida real del hueco del frente en centimetros.',
                                'opciones' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_mueble_bano',
                    'orden' => 3,
                    'obligatorio' => true,
                    'configurador' => [
                        'codigo' => 'web_mueble_bano',
                        'tipo' => 'simple',
                        'campos' => [
                            [
                                'codigo' => 'ancho',
                                'etiqueta' => 'Ancho del mueble',
                                'tipo' => 'entero',
                                'obligatorio' => true,
                                'orden' => 1,
                                'opciones' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function configBanoConRevestimientosDerivables(): array
    {
        $config = $this->configBanoBase();
        $config['componentes'][0]['configurador']['campos'][] = [
            'codigo' => 'altura',
            'etiqueta' => 'Altura del baño',
            'tipo' => 'entero',
            'obligatorio' => true,
            'orden' => 3,
            'opciones' => [],
        ];
        $config['componentes'][] = [
            'codigo' => 'web_revestimientos',
            'orden' => 2,
            'obligatorio' => true,
            'configurador' => [
                'codigo' => 'web_revestimientos',
                'tipo' => 'simple',
                'campos' => [
                    [
                        'codigo' => 'azulejo',
                        'etiqueta' => 'Azulejo necesarios',
                        'tipo' => 'booleano',
                        'obligatorio' => true,
                        'orden' => 1,
                        'opciones' => [],
                    ],
                    [
                        'codigo' => 'metros_azulejo',
                        'etiqueta' => 'Metros de azulejo necesarios',
                        'tipo' => 'decimal',
                        'obligatorio' => true,
                        'orden' => 2,
                        'opciones' => [],
                    ],
                    [
                        'codigo' => 'metros_suelo',
                        'etiqueta' => 'Metros de suelo necesarios',
                        'tipo' => 'decimal',
                        'obligatorio' => true,
                        'orden' => 3,
                        'opciones' => [],
                    ],
                ],
            ],
        ];

        return $config;
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

    private function configConCampoNumericoUnico(int|float|null $minimo = null, int|float|null $maximo = null): array
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
                                'valorMinimo' => $minimo,
                                'valorMaximo' => $maximo,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function configConPlatoYMamparaReal(): array
    {
        $config = $this->configBase();
        $config['componentes'][] = [
            'codigo' => 'web_mampara',
            'orden' => 2,
            'obligatorio' => true,
            'configurador' => [
                'codigo' => 'web_mampara',
                'tipo' => 'simple',
                'campos' => [
                    [
                        'codigo' => 'tipo',
                        'etiqueta' => 'Tipo',
                        'tipo' => 'seleccion',
                        'obligatorio' => true,
                        'orden' => 1,
                        'opciones' => [
                            ['codigo' => 'frente', 'etiqueta' => 'La mampara esta entre dos paredes'],
                            ['codigo' => 'frente_con_lateral_fijo', 'etiqueta' => 'Mampara que tiene un frente y un lateral'],
                            ['codigo' => 'fijo', 'etiqueta' => 'Cristal fijo'],
                        ],
                    ],
                    [
                        'codigo' => 'ancho_frente',
                        'etiqueta' => 'Ancho del frente',
                        'tipo' => 'entero',
                        'obligatorio' => true,
                        'orden' => 2,
                        'ayuda' => 'Medida real del hueco del frente en centimetros.',
                        'opciones' => [],
                    ],
                ],
            ],
        ];

        return $config;
    }

    private function configConPlatoMamparaManoObraYRevestimientos(): array
    {
        $config = $this->configConPlatoYMamparaReal();
        $config['componentes'][] = [
            'codigo' => 'web_mano_obra_ducha',
            'orden' => 3,
            'obligatorio' => true,
            'configurador' => [
                'codigo' => 'web_mano_obra_ducha',
                'tipo' => 'simple',
                'campos' => [
                    [
                        'codigo' => 'tipo_trabajo',
                        'etiqueta' => 'Tipo de trabajo',
                        'tipo' => 'seleccion',
                        'obligatorio' => true,
                        'orden' => 1,
                        'opciones' => [
                            ['codigo' => 'plato_por_plato', 'etiqueta' => 'Plato por plato'],
                            ['codigo' => 'banera_plato_cenefa', 'etiqueta' => 'Plato por banera hasta cenefa'],
                            ['codigo' => 'banera_plato_zona_ducha', 'etiqueta' => 'Plato por banera hasta techo'],
                        ],
                    ],
                ],
            ],
        ];
        $config['componentes'][] = [
            'codigo' => 'web_revestimientos',
            'orden' => 4,
            'obligatorio' => true,
            'configurador' => [
                'codigo' => 'web_revestimientos',
                'tipo' => 'simple',
                'campos' => [
                    [
                        'codigo' => 'azulejo',
                        'etiqueta' => 'Azulejo necesarios',
                        'tipo' => 'booleano',
                        'obligatorio' => true,
                        'orden' => 1,
                        'opciones' => [],
                    ],
                    [
                        'codigo' => 'metros_azulejo',
                        'etiqueta' => 'Metros de azulejo necesarios',
                        'tipo' => 'entero',
                        'obligatorio' => true,
                        'orden' => 2,
                        'opciones' => [],
                    ],
                ],
            ],
        ];

        return $config;
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

    private function valoresDerivadosMamparaDesdePlato(): array
    {
        return [
            'web_ducha' => [
                'web_mampara.ancho_frente' => [
                    'origen' => 'selector_plato_ducha.largo',
                    'condicion' => [
                        'campo' => 'web_mampara.tipo',
                        'valor' => 'frente',
                    ],
                ],
            ],
        ];
    }

    private function valoresDerivadosMetrosAzulejo(): array
    {
        return [
            'web_ducha' => [
                'web_revestimientos.metros_azulejo' => [
                    [
                        'expresion' => '((selector_plato_ducha.largo + selector_plato_ducha.ancho + selector_plato_ducha.ancho) / 100) * 2.40',
                        'condiciones' => [
                            ['campo' => 'web_revestimientos.azulejo', 'valor' => true],
                            ['campo' => 'web_mano_obra_ducha.tipo_trabajo', 'valor' => 'banera_plato_zona_ducha'],
                            ['campo' => 'web_mampara.tipo', 'valor' => 'frente'],
                        ],
                    ],
                ],
            ],
        ];
    }
}

final class FakeWebDuchaBudgetFlowProvider implements WebDuchaBudgetFlowProviderInterface
{
    public bool $fallarConfigurador = false;
    public bool $fallarDefinicion = false;
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

    public function obtenerDefinicion(): ?array
    {
        if ($this->fallarDefinicion) {
            throw new \RuntimeException('Definicion offline');
        }

        if ($this->fallarConfigurador) {
            throw new \RuntimeException('BudgetFlow offline');
        }

        return $this->configurador['__usar_como_definicion'] ?? false ? $this->configurador : null;
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

            $configuradorComponente = $componente['configurador'] ?? $componente;

            foreach (($configuradorComponente['campos'] ?? []) as $campo) {
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
    public int $preguntasConversacionales = 0;
    public int $interpretacionesConversacionales = 0;
    public array $interpretacion = [
        'valores_detectados' => [],
        'requiere_aclaracion' => true,
        'situacion' => 'AMBIGUA',
    ];
    public array $preguntaConversacional = [];
    public array $interpretacionConversacional = [
        'valores' => [],
        'requiere_aclaracion' => true,
        'situacion' => 'AMBIGUA',
    ];
    public array $ultimosCamposInterpretacion = [];
    public array $ultimoContextoPreguntaConversacional = [];
    public array $ultimoContextoInterpretacionConversacional = [];
    public array $contextosPreguntaConversacional = [];

    public function generarPregunta(array $campos, array $valoresConocidos): string
    {
        if ($this->fallarPregunta) {
            throw new \RuntimeException('IA offline');
        }

        return 'Pregunta generada por IA';
    }

    public function generarPreguntaConversacional(array $contexto): array
    {
        $this->preguntasConversacionales++;
        $this->ultimoContextoPreguntaConversacional = $contexto;
        $this->contextosPreguntaConversacional[] = $contexto;

        if ($this->fallarPregunta) {
            throw new \RuntimeException('IA offline');
        }

        return $this->preguntaConversacional;
    }

    public function interpretarRespuesta(array $campos, string $respuesta, array $valoresConocidos): array
    {
        $this->interpretaciones++;
        $this->ultimosCamposInterpretacion = $campos;

        return $this->interpretacion;
    }

    public function interpretarRespuestaConversacional(array $contexto, string $respuesta): array
    {
        $this->interpretacionesConversacionales++;
        $this->ultimoContextoInterpretacionConversacional = $contexto;

        return $this->interpretacionConversacional;
    }
}

final class DiagnosticArrayLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
