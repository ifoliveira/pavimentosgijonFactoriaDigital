<?php

namespace App\Tests\Controller;

use App\Controller\CestasController;
use App\Controller\ProductosController;
use App\Entity\Cestas;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\RouterInterface;

final class LegacyTicketCreationTest extends KernelTestCase
{
    /** @dataProvider creationRequests */
    public function testBloqueaLaCreacionDirecta(string $path, string $method): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod($method);
        $route = $router->match($path);
        [$class, $action] = explode('::', $route['_controller']);
        $controller = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $this->expectException(GoneHttpException::class);
        $controller->$action();
    }

    public function creationRequests(): iterable
    {
        foreach (['/admin/cestas/new', '/admin/cestas/123/edit', '/admin/detallecesta/nuevo/detalle'] as $path) {
            foreach (['GET', 'POST'] as $method) {
                yield [$path, $method];
            }
        }
    }

    public function testProductosRedirigeAlCatalogoSinCrearCesta(): void
    {
        self::bootKernel();
        $container = new Container();
        $container->set('router', self::getContainer()->get(RouterInterface::class));
        $controller = (new \ReflectionClass(ProductosController::class))->newInstanceWithoutConstructor();
        $controller->setContainer($container);
        self::assertSame('/admin/productos/lista', $controller->index()->getTargetUrl());
    }

    /** @dataProvider printingActions */
    public function testNoImprimeCestasAbiertas(string $action): void
    {
        $controller = (new \ReflectionClass(CestasController::class))->newInstanceWithoutConstructor();
        $this->expectException(GoneHttpException::class);
        $controller->$action(new Request(), new Cestas());
    }

    public function printingActions(): iterable
    {
        yield ['imprimir'];
        yield ['epsonimp'];
    }

    public function testLasRutasDeConsultaSiguenDisponibles(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertSame('cestas_index', $router->match('/admin/cestas/')['_route']);
        self::assertSame('cestas_show', $router->match('/admin/cestas/123')['_route']);
        self::assertSame('productos_lista', $router->match('/admin/productos/lista')['_route']);
    }
}
