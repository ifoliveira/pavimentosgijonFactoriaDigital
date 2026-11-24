<?php

namespace App\Tests\Service;

use App\Entity\{Documento, DocumentoLinea, Proyecto, ProyectoCobro};
use App\Repository\DocumentoRepository;
use App\Service\Documento\SerieService;
use App\Service\Proyecto\{FacturaFinalService, ProyectoCalculatorService};
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class FacturaFinalServiceTest extends TestCase
{
    public function testConservaNegativosYDescuentaAnticiposConSusCentimosExactos(): void
    {
        $proyecto = new Proyecto();
        $inicial = $this->documento('1000.00', '210.00');
        $complementario = $this->documento('-100.00', '-21.00');
        $anticipo = $this->documento('88.42', '18.57')->setTipoDocumento('factura')->setSerie('F2026')->setNumero(12)->setTotal('106.99');
        $otro = $this->documento('82.64', '17.36')->setTipoDocumento('factura')->setSerie('F2026')->setNumero(13)->setTotal('100.00');
        $proyecto->addCobro((new ProyectoCobro())->setFacturaAnticipo($anticipo));
        $proyecto->addCobro((new ProyectoCobro())->setFacturaAnticipo($otro));

        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($callback) => $callback());
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findPresupuestosAceptadosDeProyecto')->willReturn([$inicial, $complementario]);
        $service = new FacturaFinalService($em, $repo, $this->createMock(SerieService::class), $this->createMock(ProyectoCalculatorService::class));
        $final = $service->emitir($proyecto);

        self::assertCount(4, $final->getLineas());
        self::assertSame('728.94', $final->getBaseImponible());
        self::assertSame('153.07', $final->getTotalIva());
        self::assertSame('882.01', $final->getTotal());
        self::assertSame('900.00', bcadd(bcadd($final->getBaseImponible(), '88.42', 2), '82.64', 2));
        self::assertSame('189.00', bcadd(bcadd($final->getTotalIva(), '18.57', 2), '17.36', 2));
        self::assertStringContainsString('F2026-0012', $final->getLineas()[2]->getDescripcion());
        self::assertStringContainsString('106,99', $final->getLineas()[2]->getDescripcion());
        self::assertSame('-1.000', $final->getLineas()[1]->getCantidad());
        self::assertFalse($final->getLineas()[0]->isAfectaStock());
        self::assertSame('88.42', $anticipo->getLineas()[0]->getSubtotal());
        self::assertSame('pendiente', $final->getEstadoCobro());
    }

    public function testRepetirEmisionDevuelveLaFacturaExistente(): void
    {
        $final = new Documento();
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($callback) => $callback());
        $em->expects(self::never())->method('persist');
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn($final);
        $series = $this->createMock(SerieService::class);
        $series->expects(self::never())->method('asignarNumeracion');
        self::assertSame($final, (new FacturaFinalService($em, $repo, $series, $this->createMock(ProyectoCalculatorService::class)))->emitir(new Proyecto()));
    }

    /** @dataProvider anticipos */
    public function testFacturaSinAnticiposOTotalmenteAnticipada(bool $anticipada, string $total, string $estado): void
    {
        $proyecto = new Proyecto();
        $presupuesto = $this->documento('1000.00', '210.00');
        if ($anticipada) {
            $anticipo = $this->documento('1000.00', '210.00')
                ->setTipoDocumento('factura')->setSerie('F2026')->setNumero(1)->setTotal('1210.00');
            $proyecto->addCobro((new ProyectoCobro())->setFacturaAnticipo($anticipo));
        }
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($fn) => $fn());
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findPresupuestosAceptadosDeProyecto')->willReturn([$presupuesto]);
        $final = (new FacturaFinalService($em, $repo, $this->createMock(SerieService::class), $this->createMock(ProyectoCalculatorService::class)))->emitir($proyecto);
        self::assertSame($total, $final->getTotal());
        self::assertSame($estado, $final->getEstadoCobro());
    }

    public function anticipos(): array
    {
        return [[false, '1210.00', 'pendiente'], [true, '0.00', 'cobrado']];
    }

    private function documento(string $base, string $iva): Documento
    {
        $negativo = bccomp($base, '0', 2) < 0;
        $documento = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado');
        $documento->addLinea((new DocumentoLinea())
            ->setTipoLinea('servicio')->setDescripcion('Concepto')
            ->setCantidad($negativo ? '-1.000' : '1.000')
            ->setPrecioUnitario(ltrim($base, '-'))
            ->setSubtotal($base)->setTotalIva($iva)->setTipoIva('21.00')
            ->setCosteUnitario('0.00')->setTotalCoste('0.00'));
        return $documento;
    }
}
