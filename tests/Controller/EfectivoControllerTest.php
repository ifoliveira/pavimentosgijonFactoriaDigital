<?php

namespace App\Tests\Controller;

use App\Controller\EfectivoController;
use App\Entity\CajaRecuento;
use App\Entity\Efectivo;
use App\Repository\EfectivoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

class EfectivoControllerTest extends KernelTestCase
{
    private EfectivoController $controller;
    private EfectivoRepository $efectivos;
    private EntityManagerInterface $em;
    private Session $session;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $twig = $container->get(Environment::class);
        $twig->setLoader(new ChainLoader([new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']), $twig->getLoader()]));
        $this->em = $this->createMock(EntityManagerInterface::class);
        $recuentos = $this->createMock(ObjectRepository::class);
        $recuentos->method('findBy')->willReturn([]);
        $this->em->method('getRepository')->with(CajaRecuento::class)->willReturn($recuentos);
        $this->efectivos = $this->createMock(EfectivoRepository::class);
        $this->efectivos->method('saldoCentimos')->willReturn(78000);
        $this->efectivos->method('findBy')->willReturn([]);
        $this->controller = new EfectivoController($this->em);
        $this->controller->setContainer($container);
        $this->session = new Session(new MockArraySessionStorage());
        $this->session->set('caja_formulario', 'formulario-prueba');
    }

    private function request(array $datos = [], string $method = 'POST'): Request
    {
        $request = Request::create('/admin/efectivo/', $method, $datos + ['nonce' => 'formulario-prueba']);
        $request->setSession($this->session);
        self::getContainer()->get('request_stack')->push($request);
        if ($method === 'POST' && !$request->request->has('_token')) {
            $request->request->set('_token', self::getContainer()->get('security.csrf.token_manager')->getToken('caja')->getValue());
        }
        return $request;
    }

    public function testSalidaDescuentaYReenvioNoDuplica(): void
    {
        $this->em->expects(self::once())->method('persist')->with(self::callback(fn ($e) => $e instanceof Efectivo && $e->getImporteEf() === -45.25 && $e->getConceptoEf() === 'Gasolina'));
        $this->em->expects(self::once())->method('flush');
        $datos = ['accion' => 'salida', 'importe' => '45,25', 'concepto' => 'Gasolina'];
        self::assertSame(302, $this->controller->index($this->request($datos), $this->efectivos)->getStatusCode());
        self::assertStringContainsString('734,75', implode(' ', $this->session->getFlashBag()->peek('success')));
        self::assertSame(422, $this->controller->index($this->request($datos), $this->efectivos)->getStatusCode());
    }

    public function testRecuentoGuardaDiferenciaSinCrearMovimiento(): void
    {
        $this->em->expects(self::once())->method('persist')->with(self::callback(fn ($e) => $e instanceof CajaRecuento && $e->getDiferenciaCentimos() === -500));
        $this->em->expects(self::once())->method('flush');
        self::assertSame(302, $this->controller->index($this->request(['accion' => 'recuento', 'contado' => '775', 'saldo' => '78000']), $this->efectivos)->getStatusCode());
    }

    /** @dataProvider invalidos */
    public function testDatosInvalidosNoEscriben(array $datos): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');
        self::assertSame(422, $this->controller->index($this->request($datos), $this->efectivos)->getStatusCode());
    }

    public static function invalidos(): iterable
    {
        foreach (['-1', '0', '1.234', 'NaN', '1e2', ['25']] as $importe) {
            yield [['accion' => 'salida', 'importe' => $importe, 'concepto' => 'Material']];
        }
        yield [['accion' => 'salida', 'importe' => '20', 'concepto' => ' ']];
        yield [['accion' => 'recuento', 'contado' => '775', 'saldo' => '79000']];
    }

    public function testCsrfInvalidoNoEscribe(): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->controller->index($this->request(['_token' => 'invalido']), $this->efectivos);
    }

    public function testPantallaMuestraSaldoYDosAcciones(): void
    {
        $response = $this->controller->index($this->request([], 'GET'), $this->efectivos);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('780,00', $response->getContent());
        self::assertStringContainsString('Registrar salida', $response->getContent());
        self::assertStringContainsString('Guardar recuento', $response->getContent());
    }
}
