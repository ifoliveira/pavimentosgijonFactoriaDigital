<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;
use App\Service\Dashboard\DashboardProyectoService;
use PHPUnit\Framework\TestCase;

class DashboardTodosProyectosTest extends TestCase
{
    public function testExcluyeRechazadosPeroMantieneObrasConComplementariosRechazados(): void
    {
        $rechazado = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('rechazado');
        $descartado = new Proyecto();
        $descartado->addDocumento($rechazado);
        $aceptado = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado');
        $activo = new Proyecto();
        $activo->addDocumento($aceptado);
        $activo->addDocumento((new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('rechazado'));

        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('findTodosProyectosDashboard')->willReturn([$descartado, $activo]);
        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findPresupuestoInicialDeProyecto')->willReturnCallback(
            static fn (Proyecto $p): Documento => $p === $descartado ? $rechazado : $aceptado
        );

        $items = (new DashboardProyectoService($proyectos, $documentos))->getProyectosOperativos();

        self::assertSame([$activo], array_column($items, 'proyecto'));
    }

    public function testSoloEntranPresupuestosAceptadosAunqueTenganComplementariosPendientes(): void
    {
        $proyectosPorEstado = [];
        foreach (['borrador', 'enviado', 'entregado', 'rechazado', 'aceptado', 'convertido'] as $estado) {
            $proyecto = new Proyecto();
            $proyecto->addDocumento((new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial($estado));
            $proyectosPorEstado[$estado] = $proyecto;
        }
        $proyectosPorEstado['aceptado']->addDocumento(
            (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('entregado')
        );
        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('findTodosProyectosDashboard')->willReturn(array_values($proyectosPorEstado));
        $documentos = $this->createMock(DocumentoRepository::class);

        $items = (new DashboardProyectoService($proyectos, $documentos))->getProyectosOperativos();

        self::assertSame(
            [$proyectosPorEstado['aceptado'], $proyectosPorEstado['convertido']],
            array_column($items, 'proyecto')
        );
    }

    public function testRechazoDependeDeTodosLosPresupuestosEnPanelYFiltros(): void
    {
        $proyecto = new Proyecto();
        $rechazado = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('rechazado');
        $vigente = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('borrador');
        $proyecto->addDocumento($rechazado)->addDocumento($vigente);
        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('findTodosProyectosDashboard')->willReturn([$proyecto]);
        $proyectos->method('buscarProyectosDashboard')->willReturn([$proyecto]);
        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findPresupuestoInicialDeProyecto')->willReturn($rechazado);
        $service = new DashboardProyectoService($proyectos, $documentos);

        self::assertCount(0, $service->getProyectosOperativos());
        self::assertCount(1, $service->buscarProyectos(null, null, null, 'presupuesto_borrador'));
        self::assertCount(0, $service->buscarProyectos(null, null, null, 'presupuesto_rechazado'));
        $vigente->setEstadoComercial('rechazado');
        self::assertCount(0, $service->getProyectosOperativos());
        self::assertCount(0, $service->buscarProyectos(null, null, null, 'atencion'));
        self::assertCount(1, $service->buscarProyectos(null, null, null, 'presupuesto_rechazado'));
        $vigente->setEstadoComercial('entregado');
        self::assertCount(1, $service->buscarProyectos(null, null, null, 'presupuesto_entregado'));
        self::assertCount(0, $service->buscarProyectos(null, null, null, 'presupuesto_rechazado'));
    }

    public function testIncluyeObrasAceptadasOFacturadasYSeparaObraCobroYFacturaFinal(): void
    {
        $sinDocumentos = new Proyecto();
        $sinCobros = (new Proyecto())->setTotalPresupuestado('3000.00');
        $conAnticipo = (new Proyecto())->setTotalPresupuestado('3000.00')
            ->setTotalFacturado('1000.00')->setTotalCobrado('1000.00');
        $anticipo = (new Documento())->setTipoDocumento('factura')->setTotal('1000.00');
        $conAnticipo->addDocumento($anticipo);
        $conAnticipo->addCobro((new ProyectoCobro())->setFacturaAnticipo($anticipo));

        $finalizadaConDeuda = (new Proyecto())->setTotalFacturado('3000.00')->setTotalCobrado('1000.00');
        $final = (new Documento())->setTipoDocumento('factura')->setTotal('2000.00');
        $finalizadaConDeuda->addDocumento($final);
        $finalizadaConDeuda->finalizarObra();
        $cobradaSinFinalizar = (new Proyecto())->setTotalPresupuestado('3000.00')->setTotalCobrado('3000.00');
        foreach ([$sinCobros, $cobradaSinFinalizar] as $obra) {
            $obra->addDocumento((new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial('aceptado'));
        }
        $pendienteAceptar = new Proyecto();
        $pendienteAceptar->addDocumento(
            (new Documento())
                ->setTipoDocumento('presupuesto')
                ->setEstadoComercial('entregado')
        );

        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->expects(self::exactly(2))->method('findTodosProyectosDashboard')
            ->willReturn([$sinDocumentos, $sinCobros, $conAnticipo, $finalizadaConDeuda, $cobradaSinFinalizar, $pendienteAceptar]);
        $proyectos->expects(self::never())->method('findUltimosProyectosDashboard');
        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findFacturaDeProyecto')->willReturnCallback(
            static fn (Proyecto $p): ?Documento => $p === $conAnticipo ? $anticipo : ($p === $finalizadaConDeuda ? $final : null)
        );
        $documentos->method('findFacturaCompletaDeProyecto')->willReturnCallback(
            static fn (Proyecto $p): ?Documento => $p === $finalizadaConDeuda ? $final : null
        );

        $items = (new DashboardProyectoService($proyectos, $documentos))->getProyectosOperativos();

        self::assertCount(4, $items);
        self::assertNotContains($sinDocumentos, array_column($items, 'proyecto'));
        self::assertSame('pendiente', $items[0]['estadoCobro']);
        self::assertSame('parcial', $items[1]['estadoCobro']);
        self::assertSame(2000.0, $items[1]['pendiente']);
        self::assertNull($items[1]['facturaFinal']);
        self::assertTrue($items[2]['obraFinalizada']);
        self::assertSame('parcial', $items[2]['estadoCobro']);
        self::assertSame($final, $items[2]['facturaFinal']);
        self::assertSame(2000.0, $items[2]['pendiente']);
        self::assertFalse($items[3]['obraFinalizada']);
        self::assertSame('cobrado', $items[3]['estadoCobro']);
        self::assertNull($items[3]['facturaFinal']);
        self::assertNotContains($pendienteAceptar, array_column($items, 'proyecto'));

        // Finalizada + cobrada sin factura final sigue visible; con las tres condiciones desaparece.
        $cobradaSinFinalizar->finalizarObra();
        $finalizadaConDeuda->setTotalCobrado('3000.00');
        $items = (new DashboardProyectoService($proyectos, $documentos))->getProyectosOperativos();
        self::assertCount(3, $items);
        self::assertNotContains($finalizadaConDeuda, array_column($items, 'proyecto'));
        self::assertContains($cobradaSinFinalizar, array_column($items, 'proyecto'));
        self::assertNotContains($pendienteAceptar, array_column($items, 'proyecto'));
    }
}
