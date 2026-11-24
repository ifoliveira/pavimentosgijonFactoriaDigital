<?php

namespace App\Tests\Controller;

use App\Controller\BolsaGastosController;
use App\Entity\BolsaGastosAjuste;
use App\Entity\BolsaGastosConfiguracion;
use App\Repository\BolsaGastosAjusteRepository;
use App\Service\Tesoreria\BolsaGastosService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

class BolsaGastosControllerTest extends KernelTestCase
{
    private BolsaGastosController $controller;
    private BolsaGastosConfiguracion $config;
    private BolsaGastosService $service;
    private BolsaGastosAjusteRepository $ajustes;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $twig = $container->get(Environment::class);
        $twig->setLoader(new ChainLoader([new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']), $twig->getLoader()]));
        $this->controller = new BolsaGastosController();
        $this->controller->setContainer($container);
        $this->config = new BolsaGastosConfiguracion();
        $this->service = $this->createMock(BolsaGastosService::class);
        $this->service->method('getConfiguracion')->willReturn($this->config);
        $this->service->method('getResumen')->willReturnCallback(fn () => [
            'activo' => $this->config->getFechaInicio() !== null && $this->config->getFechaInicio() <= new \DateTimeImmutable('today'),
            'fechaCorte' => new \DateTimeImmutable('today'),
            'saldoBase' => 100, 'saldoIva' => 21, 'saldoConIva' => 121,
            'aportacionesBase' => 100, 'consumidoBase' => 0, 'ajustesBase' => 0,
            'excesoFacturasBase' => 0, 'movimientos' => [], 'incidencias' => [],
        ]);
        $this->ajustes = $this->createMock(BolsaGastosAjusteRepository::class);
        $this->ajustes->method('findHasta')->willReturn([]);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    public function testInicioVacioNoActivaNiEscribeYLaPantallaSeRenderiza(): void
    {
        $request = $this->request('GET');
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');
        $response = $this->controller->index($request, $this->service, $this->ajustes, $this->em);
        self::assertSame(200, $response->getStatusCode());
        $crawler = new Crawler($response->getContent());
        self::assertSame('', $crawler->filter('input[name="fechaInicio"]')->attr('value'));
        self::assertStringContainsString('La bolsa está desactivada', $response->getContent());
    }

    public function testGuardarConfiguracionPermiteDejarFechaVacia(): void
    {
        $request = $this->request('POST', $this->datos(['proveedoresExcluidos' => "Energía\nAlquiler"]));
        $this->em->expects(self::once())->method('persist')->with($this->config);
        $this->em->expects(self::once())->method('flush');
        $response = $this->controller->index($request, $this->service, $this->ajustes, $this->em);
        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->config->getFechaInicio());
        self::assertSame(['Energía', 'Alquiler'], $this->config->getProveedoresExcluidos());
        self::assertSame('10.50', $this->config->getIvaEstimado());
    }

    /** @dataProvider entradasInvalidas */
    public function testRechazaEntradaInvalidaSinCambios(array $cambios): void
    {
        $request = $this->request('POST', $this->datos($cambios));
        $this->em->expects(self::never())->method('flush');
        $response = $this->controller->index($request, $this->service, $this->ajustes, $this->em);
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('No se han guardado los cambios', $response->getContent());
        self::assertNull($this->config->getFechaInicio());
        self::assertSame('21.00', $this->config->getIvaEstimado());
    }

    public static function entradasInvalidas(): iterable
    {
        yield [['fechaInicio' => '2027-02-30']];
        yield [['ivaEstimado' => '21 euros']];
        yield [['ivaEstimado' => '101']];
        yield [['plazoPagoDias' => '-1']];
        yield [['proveedoresExcluidos' => str_repeat('a', 256)]];
        yield [['fechaInicio' => ['2027-01-01']]];
    }

    public function testAjusteGlobalSeGuardaConImporteYMotivo(): void
    {
        $this->config->setFechaInicio(new \DateTimeImmutable('yesterday'));
        $request = $this->request('POST', [
            'accion' => 'crear_ajuste', 'fecha' => date('Y-m-d'), 'importeBase' => '-50,25', 'motivo' => 'Ahorro previsto',
        ], 'bolsa_gastos_ajuste');
        $this->em->expects(self::once())->method('persist')->with(self::callback(static fn ($entity) => $entity instanceof BolsaGastosAjuste && $entity->getImporteBase() === '-50.25' && $entity->getMotivo() === 'Ahorro previsto'));
        $this->em->expects(self::once())->method('flush');
        self::assertSame(302, $this->controller->index($request, $this->service, $this->ajustes, $this->em)->getStatusCode());
    }

    public function testTokenInvalidoNoEscribe(): void
    {
        $request = $this->request('POST', $this->datos());
        $request->request->set('_token', 'invalido');
        $this->em->expects(self::never())->method('flush');
        $this->expectException(AccessDeniedException::class);
        $this->controller->index($request, $this->service, $this->ajustes, $this->em);
    }

    private function request(string $method, array $datos = [], string $token = 'bolsa_gastos_configuracion'): Request
    {
        $request = Request::create('/admin/flujocaja/bolsa', $method, $datos);
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);
        if ($method === 'POST') {
            $request->request->set('_token', self::getContainer()->get('security.csrf.token_manager')->getToken($token)->getValue());
        }
        return $request;
    }

    private function datos(array $cambios = []): array
    {
        return array_replace(['accion' => 'guardar_configuracion', 'fechaInicio' => '', 'ivaEstimado' => '10,50', 'plazoPagoDias' => '30', 'plazoObraDias' => '60', 'proveedoresExcluidos' => ''], $cambios);
    }
}
