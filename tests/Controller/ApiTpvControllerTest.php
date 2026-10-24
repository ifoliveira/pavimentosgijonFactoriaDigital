<?php

namespace App\Tests\Controller;

use App\Controller\ApiTpvController;
use App\Entity\Documento;
use App\Repository\ProductosRepository;
use App\Service\Venta\VentaDocumentoService;
use App\Service\Venta\VentaTpvInput;
use App\Service\Venta\VentaTpvResultado;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ApiTpvControllerTest extends TestCase
{
    /** @dataProvider reintentos */
    public function testMantieneElContratoYDevuelveElNumeroDeDocumento(bool $reintento, int $status): void
    {
        $documento = new Documento();
        $documento->setSerie('T2026')->setNumero(42)->setTpvUuid('550e8400-e29b-41d4-a716-446655440000')
            ->setTpvCaja('CAJA-01')->setTpvNumeroTicketLocal('LOCAL-42')->setTpvFormaPago('Tarjeta')->setTotal('20.00');
        $service = $this->createMock(VentaDocumentoService::class);
        $service->expects(self::once())->method('crearVentaTpv')
            ->with(self::callback(fn (VentaTpvInput $input) => $input->formaPago === 'Tarjeta' && $input->lineas[0]->precioAplicado === 10.0))
            ->willReturn(new VentaTpvResultado($documento, $reintento));
        $controller = new ApiTpvController($service, $this->createMock(ProductosRepository::class), '{"CAJA-01":"test-key"}');
        $response = $controller->crearVenta($this->request());
        self::assertSame($status, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(['success', 'idempotente', 'id', 'numeroTicket', 'uuid', 'numeroTicketLocal', 'caja', 'formaPago', 'total', 'estado'], array_keys($data));
        self::assertSame('T2026-0042', $data['numeroTicket']);
        self::assertSame('LOCAL-42', $data['numeroTicketLocal']);
        self::assertSame(2, $data['estado']);
        self::assertSame($reintento, $data['idempotente']);
    }

    public function testNoCreaVentasSinAutorizacion(): void
    {
        $service = $this->createMock(VentaDocumentoService::class);
        $service->expects(self::never())->method('crearVentaTpv');
        $controller = new ApiTpvController($service, $this->createMock(ProductosRepository::class), '{"CAJA-01":"test-key"}');
        $request = $this->request();
        $request->headers->remove('X-TPV-API-Key');
        self::assertSame(401, $controller->crearVenta($request)->getStatusCode());
    }

    public function reintentos(): iterable
    {
        yield [false, 201];
        yield [true, 200];
    }

    private function request(): Request
    {
        return Request::create('/api/tpv/ventas', 'POST', [], [], [], ['HTTP_X_TPV_API_KEY' => 'test-key'], json_encode([
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'fecha' => '2026-10-18T12:30:00+02:00',
            'caja' => 'CAJA-01',
            'formaPago' => 'TARJETA',
            'numeroTicketLocal' => 'LOCAL-42',
            'total' => 20,
            'lineas' => [['productoId' => 1, 'cantidad' => 2, 'precioAplicado' => 10]],
        ]));
    }
}
