<?php

namespace App\Tests\Service;

use App\Entity\Cestas;
use App\Entity\Pagos;
use App\Repository\TiposmovimientoRepository;
use App\Service\PagoService;
use App\Service\Venta\VentaTicketService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

final class VentaTicketServiceTest extends TestCase
{
    public function testNoPermiteFinalizarUnaNuevaCesta(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $pagos = $this->createMock(PagoService::class);
        $pagos->expects(self::never())->method('ticketPagoFinal');
        $service = new VentaTicketService($em, $this->createMock(TiposmovimientoRepository::class), $pagos);

        $this->expectException(GoneHttpException::class);
        $service->finalizarVentaManual(new Cestas(), 'Tarjeta', 'nuevo.pdf', 20);
    }

    public function testPermiteCobrarElPendienteDeUnTicketHistoricoSinRenumerarlo(): void
    {
        $cesta = new Cestas();
        $cesta->setEstadoCs(3);
        $cesta->setImporteTotCs(100);
        $cesta->setNumticketCs('antiguo.pdf');
        $pago = new Pagos();
        $pago->setImportePg(40);
        $cesta->getPagos()->add($pago);

        $em = $this->createMock(EntityManagerInterface::class);
        $pagos = $this->createMock(PagoService::class);
        $pagos->expects(self::once())->method('ticketPagoFinal')
            ->with($cesta, 60.0, 'Tarjeta', self::anything())->willReturn(new Pagos());

        $service = new VentaTicketService($em, $this->createMock(TiposmovimientoRepository::class), $pagos);
        $service->finalizarVentaManual($cesta, 'Tarjeta', 'no-usar.pdf', 999);

        self::assertSame(2, $cesta->getEstadoCs());
        self::assertSame('antiguo.pdf', $cesta->getNumticketCs());
        self::assertSame(100.0, $cesta->getImporteTotCs());
    }
}
