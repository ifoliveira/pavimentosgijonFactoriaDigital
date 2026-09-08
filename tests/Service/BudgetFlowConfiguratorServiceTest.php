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

    private function configuradorSimple(array $campos): array
    {
        return [
            'codigo' => 'griferia',
            'tipo' => 'simple',
            'campos' => $campos,
        ];
    }
}
