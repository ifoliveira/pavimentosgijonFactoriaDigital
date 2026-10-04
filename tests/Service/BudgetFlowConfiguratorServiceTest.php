<?php

namespace App\Tests\Service;

use App\Integration\BudgetFlow\BudgetFlowClient;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BudgetFlowConfiguratorServiceTest extends TestCase
{
    private BudgetFlowConfiguratorService $service;

    protected function setUp(): void
    {
        $client = new BudgetFlowClient($this->createMock(HttpClientInterface::class));

        $this->service = new BudgetFlowConfiguratorService($client);
    }

    public function testOmiteTextoNoInformadoDespuesDeNormalizar(): void
    {
        $configurador = $this->configuradorSimple([
            ['codigo' => 'descripcion', 'tipo' => 'texto'],
            ['codigo' => 'uso', 'tipo' => 'seleccion'],
        ]);

        self::assertSame(
            ['uso' => 'ducha'],
            $this->service->normalizarValores($configurador, [
                'descripcion' => null,
                'uso' => 'ducha',
            ])
        );

        self::assertSame(
            ['uso' => 'ducha'],
            $this->service->normalizarValores($configurador, [
                'descripcion' => '',
                'uso' => 'ducha',
            ])
        );

        self::assertSame(
            ['uso' => 'ducha'],
            $this->service->normalizarValores($configurador, [
                'descripcion' => ' ',
                'uso' => 'ducha',
            ])
        );
    }

    public function testNoOmiteValoresLegitimosAunqueSeanFalsy(): void
    {
        $configurador = $this->configuradorSimple([
            ['codigo' => 'entero_cero', 'tipo' => 'entero'],
            ['codigo' => 'texto_cero', 'tipo' => 'texto'],
            ['codigo' => 'decimal_cero', 'tipo' => 'decimal'],
            ['codigo' => 'booleano_falso', 'tipo' => 'booleano'],
        ]);

        self::assertSame(
            [
                'entero_cero' => 0,
                'texto_cero' => '0',
                'decimal_cero' => 0.0,
                'booleano_falso' => false,
            ],
            $this->service->normalizarValores($configurador, [
                'entero_cero' => '0',
                'texto_cero' => '0',
                'decimal_cero' => '0',
                'booleano_falso' => false,
            ])
        );
    }

    public function testOmiteCamposNoInformadosEnConfiguradorCompuesto(): void
    {
        $configurador = [
            'codigo' => 'ducha',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'griferia',
                    'configurador' => $this->configuradorSimple([
                        ['codigo' => 'descripcion', 'tipo' => 'texto'],
                        ['codigo' => 'uso', 'tipo' => 'seleccion'],
                        ['codigo' => 'tipo', 'tipo' => 'seleccion'],
                        ['codigo' => 'acabado', 'tipo' => 'seleccion'],
                    ]),
                ],
            ],
        ];

        self::assertSame(
            [
                'griferia' => [
                    'uso' => 'ducha',
                    'tipo' => 'monomando',
                    'acabado' => 'cromo',
                ],
            ],
            $this->service->normalizarValores($configurador, [
                'griferia' => [
                    'descripcion' => null,
                    'uso' => 'ducha',
                    'tipo' => 'monomando',
                    'acabado' => 'cromo',
                ],
            ])
        );
    }

    public function testIncluyeDefaultsEnConfiguradorCompuestoConEstructuraRealWebMampara(): void
    {
        $configurador = [
            'codigo' => 'web_ducha',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'web_mampara',
                    'configurador' => $this->configuradorSimple([
                        [
                            'codigo' => 'tipo',
                            'tipo' => 'seleccion',
                            'obligatorio' => true,
                        ],
                        [
                            'codigo' => 'ancho_frente',
                            'tipo' => 'decimal',
                            'obligatorio' => true,
                        ],
                        [
                            'codigo' => 'tipo_apertura',
                            'tipo' => 'seleccion',
                            'obligatorio' => false,
                            'valorDefecto' => 'corredera',
                        ],
                        [
                            'codigo' => 'modelo',
                            'tipo' => 'texto',
                            'obligatorio' => true,
                            'valorDefecto' => 'fresh',
                            'opciones' => [
                                ['codigo' => 'fresh', 'etiqueta' => 'Fresh'],
                            ],
                        ],
                    ]),
                ],
            ],
        ];

        self::assertSame(
            [
                'web_mampara' => [
                    'tipo' => 'fijo',
                    'ancho_frente' => 150.0,
                    'tipo_apertura' => 'corredera',
                    'modelo' => 'fresh',
                ],
            ],
            $this->service->normalizarValores($configurador, [
                'web_mampara' => [
                    'tipo' => 'fijo',
                    'ancho_frente' => 150,
                ],
            ])
        );
    }

    public function testNormalizaDefaultsSegunOrigenValorEnDefinicion(): void
    {
        $configurador = [
            'codigo' => 'web_baño',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'web_medidas_bano',
                    'campos' => [
                        [
                            'codigo' => 'alto',
                            'tipo' => 'entero',
                            'origenValor' => 'fijo',
                            'valorDefecto' => 240,
                        ],
                        [
                            'codigo' => 'largo',
                            'tipo' => 'entero',
                            'origenValor' => 'usuario',
                            'valorDefecto' => 200,
                        ],
                        [
                            'codigo' => 'ancho',
                            'tipo' => 'entero',
                            'origenValor' => 'usuario',
                            'valorDefecto' => 250,
                        ],
                    ],
                ],
                [
                    'codigo' => 'selector_plato_ducha',
                    'campos' => [
                        [
                            'codigo' => 'color',
                            'tipo' => 'seleccion',
                            'origenValor' => 'interno',
                            'valorDefecto' => 'blanco',
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_mampara',
                    'campos' => [
                        [
                            'codigo' => 'ancho_frente',
                            'tipo' => 'entero',
                            'origenValor' => 'derivado',
                            'valorDefecto' => 150,
                        ],
                    ],
                ],
                [
                    'codigo' => 'web_revestimientos',
                    'campos' => [
                        [
                            'codigo' => 'metros_suelo',
                            'tipo' => 'decimal',
                            'origenValor' => 'derivado',
                            'valorDefecto' => 1,
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame(
            [
                'web_medidas_bano' => [
                    'alto' => 240,
                    'ancho' => 300,
                ],
                'selector_plato_ducha' => [
                    'color' => 'blanco',
                ],
                'web_mampara' => [
                    'ancho_frente' => 120,
                ],
                'web_revestimientos' => [],
            ],
            $this->service->normalizarValores($configurador, [
                'web_medidas_bano' => [
                    'ancho' => 300,
                ],
                'web_mampara' => [
                    'ancho_frente' => 120,
                ],
            ])
        );
    }

    public function testDerivadoConDefaultNoEntraEnPayloadSiNoTieneValorReal(): void
    {
        $configurador = [
            'codigo' => 'web_baño',
            'tipo' => 'compuesto',
            'componentes' => [
                [
                    'codigo' => 'web_mampara',
                    'campos' => [
                        [
                            'codigo' => 'ancho_frente',
                            'tipo' => 'entero',
                            'origenValor' => 'derivado',
                            'valorDefecto' => 150,
                        ],
                    ],
                ],
            ],
        ];

        $valores = $this->service->normalizarValores($configurador, []);

        self::assertSame(
            [
                'valores' => [
                    'web_mampara' => [],
                ],
            ],
            $this->service->construirSolicitudApi($configurador, $valores)
        );
    }

    public function testCampoAntiguoSinOrigenValorConservaDefaultHistorico(): void
    {
        $configurador = $this->configuradorSimple([
            [
                'codigo' => 'modelo',
                'tipo' => 'texto',
                'valorDefecto' => 'fresh',
            ],
        ]);

        self::assertSame(
            ['modelo' => 'fresh'],
            $this->service->normalizarValores($configurador, [])
        );
    }

    public function testConstruyeSolicitudApiParaConfiguradorSimple(): void
    {
        $configurador = $this->configuradorSimple([]);

        self::assertSame(
            [
                'valores' => [
                    'griferia' => [
                        'tipo' => 'monomando',
                    ],
                ],
            ],
            $this->service->construirSolicitudApi($configurador, [
                'tipo' => 'monomando',
            ])
        );
    }

    public function testConstruyeSolicitudApiParaConfiguradorCompuesto(): void
    {
        $configurador = [
            'codigo' => 'web_ducha',
            'tipo' => 'compuesto',
        ];

        self::assertSame(
            [
                'valores' => [
                    'web_mampara' => [
                        'tipo' => 'fijo',
                    ],
                ],
            ],
            $this->service->construirSolicitudApi($configurador, [
                'web_mampara' => [
                    'tipo' => 'fijo',
                ],
            ])
        );
    }

    private function configuradorSimple(array $campos): array
    {
        return [
            'codigo' => 'griferia',
            'tipo' => 'simple',
            'campos' => $campos,
        ];
    }
}
