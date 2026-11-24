<?php

namespace App\Tests\Service;

use App\Entity\{Documento, DocumentoLinea, Proyecto};
use App\Repository\DocumentoRepository;
use App\Service\Documento\{DocumentoCalculatorService, SerieService};
use App\Service\Efectivo\EfectivoService;
use App\Service\Proyecto\{FacturaAnticipoService, FacturaFinalService, ProyectoCalculatorService, ProyectoCobroService};
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class ProyectoCobroLimiteTest extends TestCase
{
    public function testRechazaExcesoAntesDeCrearCobroOMovimiento(): void
    {
        $proyecto = (new Proyecto())->setTotalPresupuestado('3069.88')->setTotalCobrado('1069.88');
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($fn) => $fn());
        $em->expects(self::never())->method('persist');
        $efectivo = $this->createMock(EfectivoService::class);
        $efectivo->expects(self::never())->method('registrarEntrada');
        $service = $this->servicio($em, $efectivo, $this->createMock(DocumentoRepository::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2.000,00');
        $service->registrarCobro($proyecto, new \DateTime(), 'efectivo', 2000.01);
    }

    public function testCobroExactoTrasFacturaFinalNoEmiteOtroAnticipo(): void
    {
        $proyecto = (new Proyecto())->setTotalPresupuestado('3069.88')->setTotalFacturado('3069.88')->setTotalCobrado('1069.88');
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($fn) => $fn());
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(\App\Entity\ProyectoCobro::class));
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn(new Documento());
        $service = $this->servicio($em, $this->createMock(EfectivoService::class), $repo);
        $cobro = $service->registrarCobro($proyecto, new \DateTime(), 'transferencia', 2000);
        self::assertSame('2000.00', $cobro->getImporteBruto());
        self::assertNull($cobro->getFacturaAnticipo());
    }

    public function testCobroExactoEmiteLaFacturaFinalEnLugarDeUnAnticipo(): void
    {
        $proyecto = (new Proyecto())
            ->setTotalPresupuestado('100.00')
            ->setTotalCobrado('0.00');
        $presupuesto = $this->presupuesto('82.64', '17.36');
        $em = $this->entityManager();
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn(null);
        $repo->method('findPresupuestosAceptadosDeProyecto')->willReturn([$presupuesto]);

        $cobro = $this->servicio($em, $this->createMock(EfectivoService::class), $repo, true)
            ->registrarCobro($proyecto, new \DateTime(), 'transferencia', 100);

        self::assertNull($cobro->getFacturaAnticipo());
        self::assertCount(1, $proyecto->getDocumentos());
        self::assertSame('100.00', $proyecto->getDocumentos()->first()->getTotal());
        self::assertNotNull($proyecto->getDocumentos()->first()->getResumenFacturaFinal());
    }

    public function testSolicitaConfirmacionCuandoQuedanCincoEurosOMenos(): void
    {
        $proyecto = (new Proyecto())
            ->setTotalPresupuestado('100.00')
            ->setTotalCobrado('0.00');
        $em = $this->entityManager();
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn(null);

        $this->expectException(\App\Service\Proyecto\ConfirmacionFacturaFinalRequerida::class);
        $this->expectExceptionMessage('2,00 €');

        $this->servicio($em, $this->createMock(EfectivoService::class), $repo)
            ->registrarCobro($proyecto, new \DateTime(), 'transferencia', 98);
    }

    public function testConfirmacionAplicaLaDiferenciaComoDescuentoYEmiteLaFinal(): void
    {
        $proyecto = (new Proyecto())
            ->setTotalPresupuestado('100.00')
            ->setTotalCobrado('0.00');
        $presupuesto = $this->presupuesto('82.64', '17.36');
        $em = $this->entityManager();
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn(null);
        $repo->method('findPresupuestosAceptadosDeProyecto')->willReturn([$presupuesto]);

        $cobro = $this->servicio($em, $this->createMock(EfectivoService::class), $repo, true)
            ->registrarCobro(
                $proyecto,
                new \DateTime(),
                'transferencia',
                98,
                confirmarFacturaFinal: true,
            );

        $final = $proyecto->getDocumentos()->first();
        self::assertNull($cobro->getFacturaAnticipo());
        self::assertSame('98.00', $final->getTotal());
        self::assertSame('-1.65', $final->getLineas()->last()->getSubtotal());
        self::assertSame('-0.35', $final->getLineas()->last()->getTotalIva());
    }

    public function testRechazarElCierreMantieneElCobroComoAnticipo(): void
    {
        $proyecto = (new Proyecto())
            ->setTotalPresupuestado('100.00')
            ->setTotalCobrado('0.00');
        $proyecto->addDocumento($this->presupuesto('82.64', '17.36'));
        $em = $this->entityManager();
        $repo = $this->createMock(DocumentoRepository::class);
        $repo->method('findFacturaCompletaDeProyecto')->willReturn(null);

        $cobro = $this->servicio($em, $this->createMock(EfectivoService::class), $repo, true)
            ->registrarCobro(
                $proyecto,
                new \DateTime(),
                'transferencia',
                98,
                confirmarFacturaFinal: false,
            );

        self::assertNotNull($cobro->getFacturaAnticipo());
        self::assertSame('98.00', $cobro->getFacturaAnticipo()->getTotal());
        self::assertTrue($cobro->getFacturaAnticipo()->esFacturaAnticipo());
    }

    private function servicio(
        EntityManager $em,
        EfectivoService $efectivo,
        DocumentoRepository $repo,
        bool $asignaNumeracion = false,
    ): ProyectoCobroService
    {
        $series = $this->createMock(SerieService::class);
        if ($asignaNumeracion) {
            $series->expects(self::once())->method('asignarNumeracion');
        } else {
            $series->expects(self::never())->method('asignarNumeracion');
        }
        return new ProyectoCobroService(
            $em, $this->createMock(ProyectoCalculatorService::class), $efectivo,
            new FacturaAnticipoService($em, $series, new DocumentoCalculatorService()), $repo,
            new FacturaFinalService($em, $repo, $series, $this->createMock(ProyectoCalculatorService::class))
        );
    }

    private function entityManager(): EntityManager
    {
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn ($callback) => $callback());

        return $em;
    }

    private function presupuesto(string $base, string $iva): Documento
    {
        $presupuesto = (new Documento())
            ->setTipoDocumento('presupuesto')
            ->setEstadoComercial('aceptado')
            ->setTotal(bcadd($base, $iva, 2));
        $presupuesto->addLinea((new DocumentoLinea())
            ->setTipoLinea('servicio')
            ->setDescripcion('Obra')
            ->setCantidad('1.000')
            ->setPrecioUnitario($base)
            ->setTipoIva('21.00')
            ->setSubtotal($base)
            ->setTotalIva($iva)
            ->setCosteUnitario('0.00')
            ->setTotalCoste('0.00'));

        return $presupuesto;
    }
}
