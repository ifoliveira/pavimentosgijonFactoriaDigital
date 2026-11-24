<?php

namespace App\Tests\Controller;

use App\Controller\ProyectoCobroController;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Service\Proyecto\ConfirmacionFacturaFinalRequerida;
use App\Service\Proyecto\ProyectoCobroService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

final class ProyectoCobroJustificanteTest extends KernelTestCase
{
    /** @dataProvider tiposPeticion */
    public function testGuardarAbreElJustificanteDelCobroCreado(bool $ajax, bool $error): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $proyecto = (new Proyecto())->setNombre('Reforma de prueba');
        (new \ReflectionProperty(Proyecto::class, 'id'))->setValue($proyecto, 12);
        $cobro = (new ProyectoCobro())->setProyecto($proyecto)->setImporteBruto('500.00')
            ->setMetodo('transferencia')->setFecha(new \DateTime('2026-11-15'));
        (new \ReflectionProperty(ProyectoCobro::class, 'id'))->setValue($cobro, 34);
        $service = $this->createMock(ProyectoCobroService::class);
        $expectation = $service->expects(self::once())->method('registrarCobro');
        if ($error) {
            $expectation->willThrowException(new \RuntimeException('Importe no válido.'));
        } else {
            $expectation->willReturn($cobro);
        }
        $request = new Request([], ['importeBruto' => '500', 'fecha' => '2026-11-15']);
        if ($ajax) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);
        $controller = new ProyectoCobroController();
        $controller->setContainer($container);
        $response = $controller->nuevo($proyecto, $request, $service);
        $url = $container->get(RouterInterface::class)->generate('proyecto_cobro_justificante', ['id' => 34]);
        if ($ajax) {
            $data = json_decode($response->getContent(), true);
            self::assertSame(!$error, $data['success']);
            self::assertSame($error ? 422 : 200, $response->getStatusCode());
            if ($error) {
                self::assertArrayNotHasKey('redirectUrl', $data);
            } else {
                self::assertSame($url, $data['redirectUrl']);
            }
        } else {
            self::assertSame($error ? $container->get(RouterInterface::class)->generate('app_proyecto_show', ['id' => 12]) : $url, $response->headers->get('Location'));
        }
        if (!$error) {
            $twig = $container->get(Environment::class);
            $twig->setLoader(new ChainLoader([new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']), $twig->getLoader()]));
            $html = $controller->justificante($cobro)->getContent();
            self::assertStringContainsString('500,00 €', $html);
            self::assertStringContainsString('Cobro registrado correctamente.', $html);
            self::assertStringContainsString('Imprimir justificante', $html);
            self::assertStringContainsString($container->get(RouterInterface::class)->generate('proyecto_cobro_justificante_pdf', ['id' => 34]), $html);
        }
    }

    public function tiposPeticion(): iterable
    {
        yield [true, false];
        yield [false, false];
        yield [true, true];
        yield [false, true];
    }

    public function testRespuestaAjaxIndicaQueDebeConfirmarLaFacturaFinal(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $proyecto = (new Proyecto())->setNombre('Reforma de prueba');
        (new \ReflectionProperty(Proyecto::class, 'id'))->setValue($proyecto, 12);
        $service = $this->createMock(ProyectoCobroService::class);
        $service->expects(self::once())->method('registrarCobro')
            ->willThrowException(new ConfirmacionFacturaFinalRequerida('Quedan 2,00 € pendientes.'));
        $request = new Request([], ['importeBruto' => '98', 'fecha' => '2026-11-15']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);
        $controller = new ProyectoCobroController();
        $controller->setContainer($container);

        $response = $controller->nuevo($proyecto, $request, $service);
        $data = json_decode($response->getContent(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($data['success']);
        self::assertTrue($data['requiereConfirmacionFacturaFinal']);
        self::assertSame('Quedan 2,00 € pendientes.', $data['message']);
    }
}
