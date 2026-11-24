<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;
use App\Service\Dashboard\DashboardProyectoService;
use PHPUnit\Framework\TestCase;

class DashboardSituacionTest extends TestCase
{
    public function testContadoresCoincidenConListadosYElCierreRequiereLasTresCondiciones(): void
    {
        $cerrado = $this->proyectoConFactura('3000.00');
        $cerrado->finalizarObra();
        $finalizadoConDeuda = $this->proyectoConFactura('1000.00');
        $finalizadoConDeuda->finalizarObra();
        $pendienteFinalizar = $this->proyectoConFactura('3000.00');
        $sinFacturaFinal = (new Proyecto())->setTotalPresupuestado('3000.00')
            ->setTotalFacturado('3000.00')->setTotalCobrado('3000.00');
        $anticipo = (new Documento())->setTipoDocumento('factura')->setTotal('3000.00')->setEstadoCobro('cobrado');
        $sinFacturaFinal->addDocumento($anticipo);
        $sinFacturaFinal->addCobro((new ProyectoCobro())->setFacturaAnticipo($anticipo));
        $sinFacturaFinal->finalizarObra();
        $borrador = new Proyecto();
        $sinPresupuesto = new Proyecto();
        $todos = [$cerrado, $finalizadoConDeuda, $pendienteFinalizar, $sinFacturaFinal, $borrador, $sinPresupuesto];

        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('findAll')->willReturn([]);
        $proyectos->method('buscarProyectosDashboard')->willReturnCallback(
            static function () use (&$todos): array { return $todos; }
        );
        // Los contadores antiguos no deben intervenir, aunque den cifras distintas.
        $proyectos->expects(self::never())->method('countProyectosConPresupuestoBorrador');
        $proyectos->expects(self::never())->method('countProyectosCerrados');
        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('findFacturaDeProyecto')->willReturnCallback(
            static fn (Proyecto $p): ?Documento => $p->getDocumentos()->first() ?: null
        );
        $documentos->method('findFacturaCompletaDeProyecto')->willReturnCallback(
            static function (Proyecto $p): ?Documento {
                foreach ($p->getDocumentos() as $d) {
                    if (!$d->esFacturaAnticipo()) {
                        return $d;
                    }
                }
                return null;
            }
        );
        $documentos->method('findPresupuestoInicialDeProyecto')->willReturnCallback(
            static fn (Proyecto $p): ?Documento => $p === $sinPresupuesto ? null :
                (new Documento())->setTipoDocumento('presupuesto')
                    ->setEstadoComercial(in_array($p, [$borrador, $cerrado], true) ? 'borrador' : 'aceptado')
        );
        $service = new DashboardProyectoService($proyectos, $documentos);
        $contadores = $service->getDashboardData()['contadoresSituacion'];

        self::assertSame(5, $contadores['atencion']);
        self::assertSame(1, $contadores['cerrado']);
        self::assertSame(1, $contadores['presupuesto_borrador']);
        self::assertSame(1, $contadores['facturado_pendiente']);
        self::assertSame(1, $contadores['aceptado_sin_factura']);
        self::assertSame(1, $contadores['en_proceso']);
        foreach ($contadores as $filtro => $cantidad) {
            self::assertCount($cantidad, $service->buscarProyectos(null, null, null, $filtro), $filtro);
        }

        // Reproduce el caso original: todos cerrados, ningún pendiente fantasma.
        $todos = [$cerrado];
        self::assertSame(0, $service->getDashboardData()['contadoresSituacion']['atencion']);
        self::assertSame([], $service->buscarProyectos(null, null, null, 'atencion'));
    }

    private function proyectoConFactura(string $cobrado): Proyecto
    {
        $proyecto = (new Proyecto())->setTotalFacturado('3000.00')->setTotalCobrado($cobrado);
        $proyecto->addDocumento((new Documento())->setTipoDocumento('factura')->setTotal('3000.00'));
        return $proyecto;
    }
}
