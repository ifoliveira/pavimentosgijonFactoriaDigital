<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\RouterInterface;

final class LegacyPresupuestoCreationTest extends KernelTestCase
{
    /** @dataProvider creationRequests */
    public function testCreationIsGone(string $path, string $method): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod($method);
        $route = $router->match($path);
        [$class, $action] = explode('::', $route['_controller']);

        // No dependencies are initialized: the retired action must stop before any database access.
        $controller = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        $this->expectException(GoneHttpException::class);
        $controller->$action();
    }

    public function creationRequests(): iterable
    {
        foreach (['/admin/clientes/new', '/admin/presupuestos/new'] as $path) {
            foreach (['GET', 'POST'] as $method) {
                yield [$path, $method];
            }
        }
    }

    public function testHistoricalRoutesRemainAvailable(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        foreach ([
            '/admin/presupuestos/' => 'presupuestos_index',
            '/admin/presupuestos/finalizados' => 'presupuestos_finalizados',
            '/admin/presupuestos/123' => 'presupuestos_show',
        ] as $path => $name) {
            self::assertSame($name, $router->match($path)['_route']);
        }
    }
}
