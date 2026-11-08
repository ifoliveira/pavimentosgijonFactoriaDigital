<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class BudgetFlowAvisosTemplateTest extends TestCase
{
    public function testMuestraSinCoincidenciaDesdeLaExplicacionYMarcaLaRevision(): void
    {
        $html = $this->twig()->render('budget_flow/_avisos.html.twig', [
            'avisos' => [
                [
                    'mensaje' => 'Mensaje anterior que no debe ser el principal.',
                    'diagnostico' => [
                        'motivo' => 'sin_coincidencia_por_operador_interno',
                        'operador' => 'OPERADOR-INTERNO',
                        'criterios_tecnicos' => ['CRITERIO-TECNICO-INTERNO'],
                        'explicacion' => [
                            'resumen' => 'No hay una referencia que cumpla todas las condiciones.',
                            'motivos' => [
                                ['texto' => 'La medida solicitada no está disponible.'],
                            ],
                            'restricciones' => [
                                ['texto' => 'Se mantiene el acabado seleccionado.'],
                            ],
                            'requiere_revision' => true,
                            'clasificacion_seleccion' => 'sin_coincidencia',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('No hay una referencia que cumpla todas las condiciones.', $html);
        self::assertStringNotContainsString('Mensaje anterior que no debe ser el principal.', $html);
        self::assertStringContainsString('Motivos', $html);
        self::assertStringContainsString('La medida solicitada no está disponible.', $html);
        self::assertStringContainsString('Condiciones mantenidas', $html);
        self::assertStringContainsString('Se mantiene el acabado seleccionado.', $html);
        self::assertStringContainsString('Requiere revisión', $html);
        self::assertStringContainsString('data-requiere-revision="true"', $html);
        self::assertStringContainsString('alert-danger', $html);
        self::assertStringNotContainsString('sin_coincidencia_por_operador_interno', $html);
        self::assertStringNotContainsString('OPERADOR-INTERNO', $html);
        self::assertStringNotContainsString('CRITERIO-TECNICO-INTERNO', $html);
    }

    public function testMuestraAlternativasRealesSoloConLosCamposExplicables(): void
    {
        $html = $this->twig()->render('budget_flow/_avisos.html.twig', [
            'avisos' => [
                [
                    'diagnostico' => [
                        'explicacion' => [
                            'resumen' => 'Hemos encontrado alternativas disponibles.',
                            'clasificacion_seleccion' => 'alternativa_real',
                            'alternativas' => [
                                [
                                    'nombre' => 'Mampara corredera plata',
                                    'referencia' => 'MAM-245',
                                    'precio_unitario_sin_iva' => 499.95,
                                    'diferencias' => [
                                        ['texto' => 'Incluye tratamiento antical.'],
                                        ['texto' => 'El perfil es de aluminio plata.'],
                                    ],
                                    'concepto_codigo' => 'CATALOGO-INTERNO-245',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('Alternativas disponibles', $html);
        self::assertStringContainsString('Mampara corredera plata', $html);
        self::assertStringContainsString('MAM-245', $html);
        self::assertStringContainsString('499,95 €', $html);
        self::assertStringContainsString('Incluye tratamiento antical.', $html);
        self::assertStringContainsString('El perfil es de aluminio plata.', $html);
        self::assertStringNotContainsString('CATALOGO-INTERNO-245', $html);
    }

    public function testIndicaLaReferenciaProvisionalSinPresentarlaComoCoincidenciaExacta(): void
    {
        $html = $this->twig()->render('budget_flow/_avisos.html.twig', [
            'avisos' => [[
                'diagnostico' => [
                    'explicacion' => [
                        'resumen' => 'Se ha seleccionado una referencia provisional.',
                        'clasificacion_seleccion' => 'referencia_provisional',
                        'alternativas' => [[
                            'nombre' => 'Mampara orientativa',
                            'referencia' => 'MAM-PROV-01',
                            'precio_unitario_sin_iva' => 420,
                            'diferencias' => [],
                        ]],
                    ],
                ],
            ]],
        ]);

        self::assertStringContainsString('Referencia provisional · no es una coincidencia exacta', $html);
        self::assertStringContainsString('Referencia propuesta:', $html);
        self::assertStringContainsString('MAM-PROV-01', $html);
        self::assertStringNotContainsString('Coincidencia exacta', $html);
    }

    public function testMantieneLosAvisosAntiguosCuandoNoHayExplicacion(): void
    {
        $html = $this->twig()->render('budget_flow/_avisos.html.twig', [
            'avisos' => [
                'Aviso antiguo en texto.',
                [
                    'mensaje' => 'Aviso estructurado anterior.',
                    'diagnostico' => ['motivo' => 'codigo_tecnico_que_no_se_muestra'],
                ],
            ],
        ]);

        self::assertStringContainsString('Aviso antiguo en texto.', $html);
        self::assertStringContainsString('Aviso estructurado anterior.', $html);
        self::assertStringNotContainsString('codigo_tecnico_que_no_se_muestra', $html);
    }

    private function twig(): Environment
    {
        return new Environment(new FilesystemLoader(__DIR__.'/../../templates'));
    }
}
