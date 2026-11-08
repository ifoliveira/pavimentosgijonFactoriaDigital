<?php

namespace App\Tests\Service;

use App\Service\WebPresupuesto\Admin\LeadWebBudgetFlowPresenter;
use PHPUnit\Framework\TestCase;

final class LeadWebBudgetFlowPresenterTest extends TestCase
{
    public function testConservaUnaExplicacionAunqueElAvisoNoTengaMensajeAntiguo(): void
    {
        $diagnostico = [
            'explicacion' => [
                'resumen' => 'No hay una coincidencia exacta.',
                'clasificacion_seleccion' => 'referencia_provisional',
            ],
        ];

        $presentado = (new LeadWebBudgetFlowPresenter())->presupuesto([
            'avisos' => [[
                'diagnostico' => $diagnostico,
            ]],
        ]);

        self::assertSame([
            [
                'mensaje' => 'No hay una coincidencia exacta.',
                'clasificacion_seleccion' => 'referencia_provisional',
                'diagnostico' => $diagnostico,
            ],
        ], $presentado['avisos']);
    }

    public function testMantieneAvisosAnterioresSinDiagnostico(): void
    {
        $presentado = (new LeadWebBudgetFlowPresenter())->presupuesto([
            'avisos' => [
                'Aviso antiguo.',
                ['mensaje' => 'Aviso estructurado anterior.'],
            ],
        ]);

        self::assertSame([
            [
                'mensaje' => 'Aviso antiguo.',
                'clasificacion_seleccion' => null,
                'diagnostico' => null,
            ],
            [
                'mensaje' => 'Aviso estructurado anterior.',
                'clasificacion_seleccion' => null,
                'diagnostico' => null,
            ],
        ], $presentado['avisos']);
    }
}
