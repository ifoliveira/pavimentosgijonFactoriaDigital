<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\StockReserva;
use App\Repository\DocumentoLineaRepository;
use App\Repository\ProductosRepository;
use App\Repository\StockMovimientoRepository;
use App\Repository\StockReservaRepository;
use App\Service\Documento\DescripcionPresupuestoFormatter;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Documento\DocumentoLineaService;
use App\Service\Proyecto\ProyectoCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DocumentoCosteServiceTest extends TestCase
{
    public function testCasoAActualizaElCosteUnitarioYTotalSinAlterarLaVenta(): void
    {
        $documento = $this->documentoConTotalesDeVenta();
        $linea = $this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 2, 350, 1074.38);
        $linea->setPrecioUnitario('537.1900');
        $linea->setCosteUnitarioBase('350.00');
        $linea->setPrecioCosteUnitario('423.50');
        $linea->setDescuento('0.00');
        $linea->setTipoIva('21.00');
        $linea->setTotalIva('225.62');
        $linea->setTotalCoste('700.00');

        $reserva = new StockReserva();
        $reserva->setCosteUnitario('350.00');
        $linea->setStockReserva($reserva);
        $documento->addLinea($linea);

        $this->servicioLinea()->actualizarCosteEstimado($linea, 385.0);

        self::assertSame('385.00', $linea->getCosteUnitario());
        self::assertSame('385.00', $linea->getCosteUnitarioBase());
        self::assertSame('770.00', $linea->getTotalCoste());
        self::assertSame('770.00', $documento->getTotalCoste());
        self::assertSame('2.000', $linea->getCantidad());
        self::assertSame('537.1900', $linea->getPrecioUnitario());
        self::assertSame('0.00', $linea->getDescuento());
        self::assertSame('21.00', $linea->getTipoIva());
        self::assertSame('1074.38', $linea->getSubtotal());
        self::assertSame('225.62', $linea->getTotalIva());
        self::assertSame('1300.00', $documento->getTotal());
        self::assertSame('423.50', $linea->getPrecioCosteUnitario());
        self::assertSame('350.00', $reserva->getCosteUnitario());
    }

    public function testCasoBElCosteDelDocumentoEsLaSumaDeLosCostesDeLinea(): void
    {
        $documento = new Documento();
        $documento->addLinea($this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 2, 385, 1000));
        $documento->addLinea($this->linea('servicio', DocumentoLinea::DESTINO_FACTURA_OBRA, 1, 200, 500));

        (new DocumentoCalculatorService())->recalcularCostesDocumento($documento);

        self::assertSame('770.00', $documento->getLineas()[0]->getTotalCoste());
        self::assertSame('200.00', $documento->getLineas()[1]->getTotalCoste());
        self::assertSame('970.00', $documento->getTotalCoste());
    }

    public function testCasoCEditarProductoDeFacturaActualizaElIndicadorDeMateriales(): void
    {
        $documento = new Documento();
        $linea = $this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 2, 350, 2000);
        $documento->addLinea($linea);
        $calculator = new DocumentoCalculatorService();

        self::assertSame(700.0, $calculator->analizarMaterialesFactura($documento)['costeMateriales']);

        $this->servicioLinea()->actualizarCosteEstimado($linea, 385.0);
        $resultado = $calculator->analizarMaterialesFactura($documento);

        self::assertSame(770.0, $resultado['costeMateriales']);
        self::assertSame(38.5, $resultado['porcentajeMaterialesCoste']);
    }

    public function testCasoDEditarServicioActualizaCosteGeneralPeroNoMateriales(): void
    {
        $documento = new Documento();
        $producto = $this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 2, 385, 1000);
        $servicio = $this->linea('servicio', DocumentoLinea::DESTINO_FACTURA_OBRA, 1, 100, 500);
        $documento->addLinea($producto);
        $documento->addLinea($servicio);
        $calculator = new DocumentoCalculatorService();
        $calculator->recalcularCostesDocumento($documento);
        $materialesAntes = $calculator->analizarMaterialesFactura($documento);

        $this->servicioLinea()->actualizarCosteEstimado($servicio, 200.0);
        $materialesDespues = $calculator->analizarMaterialesFactura($documento);

        self::assertSame('970.00', $documento->getTotalCoste());
        self::assertSame(770.0, $materialesAntes['costeMateriales']);
        self::assertSame($materialesAntes['costeMateriales'], $materialesDespues['costeMateriales']);
        self::assertSame($materialesAntes['porcentajeMaterialesCoste'], $materialesDespues['porcentajeMaterialesCoste']);
    }

    public function testCasoEEditarCosteConDescuentoNoAlteraNingunImporteDeVenta(): void
    {
        $documento = $this->documentoConTotalesDeVenta();
        $linea = $this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 2, 350, 966.94);
        $linea->setPrecioUnitario('537.1900');
        $linea->setDescuento('10.00');
        $linea->setTipoIva('21.00');
        $linea->setTotalIva('203.06');
        $documento->addLinea($linea);

        $this->servicioLinea()->actualizarCosteEstimado($linea, 385.0);

        self::assertSame('537.1900', $linea->getPrecioUnitario());
        self::assertSame('10.00', $linea->getDescuento());
        self::assertSame('966.94', $linea->getSubtotal());
        self::assertSame('203.06', $linea->getTotalIva());
        self::assertSame('1074.38', $documento->getBaseImponible());
        self::assertSame('225.62', $documento->getTotalIva());
        self::assertSame('1300.00', $documento->getTotal());
    }

    public function testCosteCeroNoUsaElSubtotalDeVentaComoCoste(): void
    {
        $documento = new Documento();
        $sinCoste = $this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 1, 0, 650);
        $sinCoste->setTotalCoste('650.00');
        $documento->addLinea($sinCoste);
        $documento->addLinea($this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 3, 10, 300));

        (new DocumentoCalculatorService())->recalcularCostesDocumento($documento);

        self::assertSame('0.00', $sinCoste->getTotalCoste());
        self::assertSame('30.00', $documento->getLineas()[1]->getTotalCoste());
        self::assertSame('30.00', $documento->getTotalCoste());
        self::assertSame('650.00', $sinCoste->getSubtotal());
    }

    public function testIndicadorDeMaterialesIgnoraServiciosYProductosDeTicket(): void
    {
        $documento = new Documento();
        $documento->addLinea($this->linea('producto', DocumentoLinea::DESTINO_FACTURA_OBRA, 2, 385, 1000));
        $documento->addLinea($this->linea('servicio', DocumentoLinea::DESTINO_FACTURA_OBRA, 1, 200, 500));
        $documento->addLinea($this->linea('producto', DocumentoLinea::DESTINO_TICKET_TIENDA, 1, 999, 999));

        $resultado = (new DocumentoCalculatorService())->analizarMaterialesFactura($documento);

        self::assertSame(1500.0, $resultado['baseFactura']);
        self::assertSame(770.0, $resultado['costeMateriales']);
        self::assertSame(1000.0, $resultado['ventaMateriales']);
        self::assertSame(1, $resultado['numeroMateriales']);
    }

    private function servicioLinea(): DocumentoLineaService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        return new DocumentoLineaService(
            $em,
            $this->createMock(ProductosRepository::class),
            $this->createMock(DocumentoLineaRepository::class),
            $this->createMock(ProyectoCalculatorService::class),
            $this->createMock(StockMovimientoRepository::class),
            $this->createMock(StockReservaRepository::class),
            new DescripcionPresupuestoFormatter(),
            new DocumentoCalculatorService(),
        );
    }

    private function documentoConTotalesDeVenta(): Documento
    {
        $documento = new Documento();
        $documento->setBaseImponible('1074.38');
        $documento->setTotalIva('225.62');
        $documento->setTotal('1300.00');

        return $documento;
    }

    private function linea(
        string $tipo,
        string $destino,
        float $cantidad,
        float $coste,
        float $subtotal
    ): DocumentoLinea {
        $linea = new DocumentoLinea();
        $linea->setTipoLinea($tipo);
        $linea->setDestinoFacturacion($destino);
        $linea->setCantidad(number_format($cantidad, 3, '.', ''));
        $linea->setCosteUnitario(number_format($coste, 2, '.', ''));
        $linea->setSubtotal(number_format($subtotal, 2, '.', ''));

        return $linea;
    }
}
