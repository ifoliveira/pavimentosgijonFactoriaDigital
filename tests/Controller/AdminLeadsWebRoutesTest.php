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
}
