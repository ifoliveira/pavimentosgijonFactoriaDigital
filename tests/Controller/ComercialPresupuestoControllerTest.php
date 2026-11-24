<?php

namespace App\Tests\Controller;

use App\Controller\ComercialPresupuestoController;
use App\Entity\Documento;
use App\Service\Stock\StockReservaService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ComercialPresupuestoControllerTest extends TestCase
{
    /** @dataProvider acciones */
    public function testAcciones(string $actual, array $datos, bool $valida, string $resultado): void
    {
        $documento = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial($actual);
        $documento->setNotaComercial('Contacto anterior');
        $proyecto = new \App\Entity\Proyecto();
        $proyecto->addDocumento($documento);
        $controller = $this->getMockBuilder(ComercialPresupuestoController::class)
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])->getMock();
        $controller->method('isCsrfTokenValid')->willReturn(true);
        $controller->expects(self::once())->method('addFlash')->with($valida ? 'success' : 'error', self::anything());
        $controller->method('redirectToRoute')->willReturn(new RedirectResponse('/admin/comercial/presupuestos'));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($valida ? self::once() : self::never())->method('flush');
        $stock = $this->createMock(StockReservaService::class);
        $stock->expects($valida && $resultado === 'rechazado' ? self::once() : self::never())
            ->method('liberarReservasDocumento')->with($documento);

        $controller->cambiarEstado($documento, new Request([], $datos), $em, $stock);

        self::assertSame($resultado, $documento->getEstadoComercial());
        self::assertSame($resultado === 'rechazado', $proyecto->isRechazado());
        if ($valida) {
            self::assertNotNull($documento->getFechaUltimoSeguimiento());
            self::assertStringContainsString('Contacto anterior', $documento->getNotaComercial());
            if (!empty($datos['nota_comercial'])) {
                self::assertStringContainsString($datos['nota_comercial'], $documento->getNotaComercial());
            }
        } else {
            self::assertNull($documento->getFechaUltimoSeguimiento());
            self::assertSame('Contacto anterior', $documento->getNotaComercial());
        }
        if ($valida && $resultado === 'rechazado') {
            self::assertSame('precio_alto', $documento->getMotivoPerdida());
        }
    }

    public function acciones(): iterable
    {
        yield ['borrador', ['estado' => 'entregado'], true, 'entregado'];
        yield ['entregado', ['estado' => 'seguimiento', 'nota_comercial' => 'Llamada al cliente'], true, 'entregado'];
        yield ['borrador', ['estado' => 'seguimiento', 'nota_comercial' => 'Revisar medidas'], true, 'borrador'];
        foreach (['borrador', 'entregado'] as $estado) {
            yield [$estado, ['estado' => 'rechazado', 'motivo_perdida' => 'precio_alto', 'nota_comercial' => 'Fuera de presupuesto'], true, 'rechazado'];
        }
        yield ['rechazado', ['estado' => 'entregado'], true, 'entregado'];
        yield ['entregado', ['estado' => 'seguimiento', 'nota_comercial' => '   '], false, 'entregado'];
        yield ['entregado', ['estado' => 'rechazado', 'motivo_perdida' => 'invalido'], false, 'entregado'];
        yield ['entregado', ['estado' => 'aceptado'], false, 'entregado'];
        foreach (['aceptado', 'convertido', 'aplicado'] as $estado) {
            yield [$estado, ['estado' => 'rechazado', 'motivo_perdida' => 'precio_alto'], false, $estado];
            yield [$estado, ['estado' => 'seguimiento', 'nota_comercial' => 'Contacto'], false, $estado];
        }
    }

    public function testTokenInvalidoNoModificaPresupuesto(): void
    {
        $controller = $this->getMockBuilder(ComercialPresupuestoController::class)
            ->onlyMethods(['isCsrfTokenValid'])->getMock();
        $controller->method('isCsrfTokenValid')->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $this->expectException(AccessDeniedException::class);
        $controller->cambiarEstado((new Documento())->setTipoDocumento('presupuesto'), new Request(), $em, $this->createMock(StockReservaService::class));
    }
}
