<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class AdminLeadsWebRoutesTest extends KernelTestCase
{
    public function testDashboardYDetalleTienenRutasAdmin(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        self::assertSame('/admin/leads-web', $router->generate('admin_leads_web_index'));
        self::assertSame('/admin/leads-web/123', $router->generate('admin_leads_web_show', ['id' => 123]));
    }

    public function testRutasPresupuestadoresWebDuchaYBano(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        self::assertSame('/presupuesto-ducha', $router->generate('web_presupuesto_ducha'));
        self::assertSame('/presupuestoInmediato', $router->generate('iapresupuesto'));
        self::assertSame('/api/presupuesto-ducha/start', $router->generate('api_web_presupuesto_ducha_start'));
        self::assertSame('/api/presupuesto-ducha/answer', $router->generate('api_web_presupuesto_ducha_answer'));
        self::assertSame('/presupuesto-bano', $router->generate('web_presupuesto_bano'));
        self::assertSame('/api/presupuesto-bano/start', $router->generate('api_web_presupuesto_bano_start'));
        self::assertSame('/api/presupuesto-bano/answer', $router->generate('api_web_presupuesto_bano_answer'));
        self::assertSame('/presupuesto-bano/ver/token', $router->generate('web_presupuesto_bano_ver', ['token' => 'token']));
        self::assertSame('/api/presupuesto-bano/ver/token/visualizacion', $router->generate('api_web_presupuesto_bano_visualizacion', ['token' => 'token']));
        self::assertSame('/api/presupuesto-bano/ver/token/interaccion', $router->generate('api_web_presupuesto_bano_interaccion', ['token' => 'token']));

        $legacy = $router->match('/presupuestoInmediato');
        self::assertStringContainsString('PublicController::index', $legacy['_controller']);

        $bano = $router->match('/presupuesto-bano');
        self::assertStringContainsString('WebPresupuestoBanoController::index', $bano['_controller']);
    }
}
