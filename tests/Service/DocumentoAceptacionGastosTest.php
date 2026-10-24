<?php

namespace App\Tests\Service;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Repository\DocumentoRepository;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Documento\DocumentoEstadoService;
use App\Service\Documento\SerieService;
use App\Service\Proyecto\ProyectoCalculatorService;
use App\Service\Stock\StockReservaService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class DocumentoAceptacionGastosTest extends TestCase
{
    public function testAceptacionCreaGastosPorConceptoYNoLosDuplica(): void
    {
        $proyecto = new Proyecto();
        $presupuesto = (new Documento())
            ->setTipoDocumento('presupuesto')
            ->setEstadoComercial('entregado')
            ->setCliente(new Clientes())
            ->setProyecto($proyecto);

        foreach ([
            ['producto', 'Material', '2.500', '12.34', DocumentoLinea::DESTINO_FACTURA_OBRA],
            ['servicio', 'Sin coste', '1', '0.00', DocumentoLinea::DESTINO_FACTURA_OBRA],
            ['mano_obra', 'Instalación', '3', '20.00', DocumentoLinea::DESTINO_TICKET_TIENDA],
            ['servicio', 'Ajuste', '-1', '10.00', DocumentoLinea::DESTINO_NO_FACTURABLE],
        ] as [$tipo, $descripcion, $cantidad, $coste, $destino]) {
            $presupuesto->addLinea((new DocumentoLinea())
                ->setTipoLinea($tipo)
                ->setDescripcion($descripcion)
                ->setCantidad($cantidad)
                ->setCosteUnitario($coste)
                ->setDestinoFacturacion($destino));
        }

        $repository = $this->createMock(DocumentoRepository::class);
        $repository->method('find')->with(42)->willReturn($presupuesto);
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $operation) => $operation());
        $persistidos = [];
        $em->method('persist')->willReturnCallback(static function ($entity) use (&$persistidos): void {
            if ($entity instanceof ProyectoGasto) {
                $persistidos[] = $entity;
            }
        });
        $service = new DocumentoEstadoService(
            $repository,
            $em,
            $this->createMock(SerieService::class),
            new DocumentoCalculatorService(),
            $this->createMock(ProyectoCalculatorService::class),
            $this->createMock(StockReservaService::class),
        );

        $factura = $service->aceptarYGenerarFactura(42);

        self::assertSame('factura', $factura->getTipoDocumento());
        self::assertSame('convertido', $presupuesto->getEstadoComercial());
        self::assertCount(3, $persistidos);
        self::assertCount(3, $proyecto->getGastos());
        self::assertSame(['30.85', '60.00', '-10.00'], array_map(static fn ($g) => $g->getBasePrevista(), $persistidos));
        self::assertSame(['Material', 'Instalación', 'Ajuste'], array_map(static fn ($g) => $g->getConcepto(), $persistidos));
        self::assertSame(['materiales', 'mano_obra_externa', 'varios'], array_map(static fn ($g) => $g->getCategoria(), $persistidos));
        foreach ($persistidos as $gasto) {
            self::assertSame($presupuesto, $gasto->getDocumento());
            self::assertSame($proyecto, $gasto->getProyecto());
            self::assertSame(ProyectoGasto::ESTADO_PREVISTO, $gasto->getEstado());
            self::assertSame($gasto->getBasePrevista(), $gasto->getImportePrevisto());
            self::assertNull($gasto->getImporteReal());
            self::assertNull($gasto->getTipoIvaPrevisto());
            self::assertFalse($gasto->isGeneraForecast());
            self::assertTrue($gasto->esManual());
        }

        try {
            $service->aceptarYGenerarFactura(42);
            self::fail('Un presupuesto convertido no debe aceptarse de nuevo.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Solo se puede aceptar un documento entregado.', $exception->getMessage());
        }
        self::assertCount(3, $persistidos);
    }
}
