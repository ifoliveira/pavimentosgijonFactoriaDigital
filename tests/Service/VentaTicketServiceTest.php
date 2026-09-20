<?php

namespace App\Tests\Service;

use App\Entity\Cestas;
use App\Entity\Pagos;
use App\Entity\Productos;
use App\Repository\CestasRepository;
use App\Repository\ProductosRepository;
use App\Repository\TiposmovimientoRepository;
use App\Service\PagoService;
use App\Service\Venta\VentaLineaInput;
use App\Service\Venta\VentaTicketService;
use App\Service\Venta\VentaTpvInput;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class VentaTicketServiceTest extends TestCase
{
    public function testCrearVentaTpvCreaCestaDetalleYPago(): void
    {
        $persistidos = [];
        $producto = $this->producto(123, 6.00, 12.50);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('beginTransaction');
        $em->expects(self::once())->method('commit');
        $em->expects(self::once())->method('flush');
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persistidos): void {
            $persistidos[] = $entity;
        });

        $cestasRepository = $this->mockCestasRepository();
        $cestasRepository->method('findOneBy')->willReturn(null);

        $productosRepository = $this->mockProductosRepository();
        $productosRepository->method('find')->with(123)->willReturn($producto);

        $pagoService = $this->createMock(PagoService::class);
        $pagoService->expects(self::once())
            ->method('ticketPagoFinal')
            ->with(
                self::isInstanceOf(Cestas::class),
                20.0,
                'Tarjeta',
                self::isInstanceOf(TiposmovimientoRepository::class)
            )
            ->willReturn(new Pagos());

        $service = new VentaTicketService(
            $em,
            $cestasRepository,
            $productosRepository,
            $this->mockTiposMovimientoRepository(),
            $pagoService
        );

        $cesta = $service->crearVentaTpv(new VentaTpvInput(
            '550e8400-e29b-41d4-a716-446655440000',
            new \DateTimeImmutable('2026-09-20T15:30:00+02:00'),
            'CAJA-01',
            'Tarjeta',
            [
                new VentaLineaInput(123, 2, 10.00, 12.50, null, 0.0, 'Silicona blanca', 21.0),
            ],
            'T-2026-000123',
            20.00
        ));

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $cesta->getTpvUuid());
        self::assertSame('T-2026-000123', $cesta->getTpvNumeroTicketLocal());
        self::assertSame('CAJA-01', $cesta->getTpvCaja());
        self::assertSame('Tarjeta', $cesta->getTipopagoCs());
        self::assertSame(20.0, $cesta->getImporteTotCs());
        self::assertSame(2, $cesta->getEstadoCs());
        self::assertSame('26262153000.pdf', $cesta->getNumticketCs());

        $detalle = $persistidos[0] ?? null;
        self::assertInstanceOf(\App\Entity\Detallecesta::class, $detalle);
        self::assertSame($producto, $detalle->getProductoDc());
        self::assertSame(2, $detalle->getCantidadDc());
        self::assertSame(10.0, $detalle->getPvpDc());
        self::assertSame(6.0, $detalle->getPrecioDc());
        self::assertSame(12.5, $detalle->getTpvPrecioOriginal());
        self::assertSame(21.0, $detalle->getTpvTipoIva());
    }

    public function testCrearVentaTpvEsIdempotentePorUuid(): void
    {
        $existente = new Cestas();
        $existente->setTpvUuid('550e8400-e29b-41d4-a716-446655440000');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('beginTransaction');
        $em->expects(self::never())->method('persist');

        $cestasRepository = $this->mockCestasRepository();
        $cestasRepository->method('findOneBy')->with(['tpvUuid' => '550e8400-e29b-41d4-a716-446655440000'])->willReturn($existente);

        $service = new VentaTicketService(
            $em,
            $cestasRepository,
            $this->mockProductosRepository(),
            $this->mockTiposMovimientoRepository(),
            $this->createMock(PagoService::class)
        );

        $resultado = $service->crearVentaTpv(new VentaTpvInput(
            '550e8400-e29b-41d4-a716-446655440000',
            new \DateTimeImmutable('2026-09-20T15:30:00+02:00'),
            'CAJA-01',
            'Tarjeta',
            [new VentaLineaInput(123, 1, 10.00)]
        ));

        self::assertSame($existente, $resultado);
    }

    private function producto(int $id, float $coste, float $pvp): Productos
    {
        $producto = new Productos();
        $reflection = new \ReflectionProperty(Productos::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($producto, $id);

        $producto->setDescripcionPd('Producto test');
        $producto->setPrecioPd($coste);
        $producto->setPvpPd($pvp);
        $producto->setStockPd(5);

        return $producto;
    }

    private function mockCestasRepository(): CestasRepository
    {
        return $this->getMockBuilder(CestasRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function mockProductosRepository(): ProductosRepository
    {
        return $this->getMockBuilder(ProductosRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function mockTiposMovimientoRepository(): TiposmovimientoRepository
    {
        return $this->getMockBuilder(TiposmovimientoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
