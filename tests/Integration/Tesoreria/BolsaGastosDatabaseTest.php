<?php

namespace App\Tests\Integration\Tesoreria;

use App\Entity\BolsaGastosAjuste;
use App\Entity\BolsaGastosConfiguracion;
use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\FacturaProveedor;
use App\Entity\Forecast;
use App\Entity\Proyecto;
use App\Entity\Tiposmovimiento;
use App\Repository\BolsaGastosAjusteRepository;
use App\Repository\BolsaGastosConfiguracionRepository;
use App\Repository\BolsaGastosOrigenRepository;
use App\Service\Tesoreria\BolsaGastosCalculator;
use App\Service\Tesoreria\BolsaGastosService;
use App\Tests\Integration\Venta\TpvDatabaseTestCase;
use Doctrine\ORM\Tools\SchemaTool;

final class BolsaGastosDatabaseTest extends TpvDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $schema = (new SchemaTool($this->em))->getSchemaFromMetadata(array_map(
            fn (string $clase) => $this->em->getClassMetadata($clase),
            [BolsaGastosConfiguracion::class, BolsaGastosAjuste::class, Proyecto::class, \App\Entity\ProyectoCobro::class, FacturaProveedor::class],
        ));
        foreach ($schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $key) {
                $table->removeForeignKey($key->getName());
            }
        }
        foreach ($schema->toSql($this->em->getConnection()->getDatabasePlatform()) as $sql) {
            $this->em->getConnection()->executeStatement($sql);
        }
    }

    public function testConsultasRealesConCorteEstadosPagosYObraFinalizada(): void
    {
        $configuraciones = new BolsaGastosConfiguracionRepository($this->registry);
        self::assertNull($configuraciones->obtener()->getFechaInicio());
        self::assertEquals(0, $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bolsa_gastos_configuracion'));
        $config = $configuraciones->obtener()->setFechaInicio(new \DateTimeImmutable('2027-01-01'));
        $this->em->persist($config);
        $proyecto = (new Proyecto())->setNombre('Obra terminada')->setFechaFinReal(new \DateTime('2027-01-15'));
        $this->em->persist($proyecto);
        foreach ([
            ['2026-12-31', 'aceptado', '9000'],
            ['2027-01-01', 'convertido', '1000'],
            ['2027-01-02', 'aplicado', '200'],
            ['2027-01-10', 'entregado', '9000'],
            ['2027-02-01', 'aceptado', '9000'],
        ] as $i => [$fecha, $estado, $base]) {
            $doc = (new Documento())->setTipoDocumento('presupuesto')->setSerie('P2027')->setNumero($i + 1)
                ->setCliente($this->em->getReference(Clientes::class, 1))->setProyecto($proyecto)
                ->setFechaAceptacion(new \DateTime($fecha))->setEstadoComercial($estado);
            $doc->addLinea((new DocumentoLinea())->setTipoLinea('producto')->setDescripcion('Material sin catálogo')->setCosteUnitario($base));
            $this->em->persist($doc);
        }
        $factura = (new FacturaProveedor())->setFechaFactura(new \DateTime('2027-01-10'))->setTotalBase(300)
            ->setTotalFactura(363)->setEstadoAsignacion('pendiente')->setProveedorNombre('Proveedor');
        $this->em->persist($factura);
        foreach ([['P', -200], ['C', -163]] as [$estado, $importe]) {
            $forecast = (new Forecast())->setTipoFr($this->em->getReference(Tiposmovimiento::class, 1))
                ->setConceptoFr('Factura')->setOrigenFr('Banco')->setEstadoFr($estado)
                ->setFechaFr(new \DateTime('2027-01-31'))->setImporteFr($importe);
            $factura->addForecast($forecast);
            $this->em->persist($forecast);
        }
        $this->em->persist((new FacturaProveedor())->setFechaFactura(new \DateTime('2026-12-31'))->setTotalBase(10000)->setTotalFactura(12100));
        $this->em->persist((new BolsaGastosAjuste())->setFecha(new \DateTimeImmutable('2027-01-20'))->setImporteBase('-50')->setMotivo('Ahorro'));
        $this->em->flush();
        $facturaId = $factura->getId();
        $this->em->clear();

        $service = new BolsaGastosService($configuraciones, new BolsaGastosOrigenRepository($this->em), new BolsaGastosAjusteRepository($this->registry), new BolsaGastosCalculator());
        $resumen = $service->getResumen(new \DateTimeImmutable('2027-01-31'));
        self::assertEquals(1200, $resumen['aportacionesBase']);
        self::assertEquals(300, $resumen['consumidoBase']);
        self::assertEquals(850, $resumen['saldoBase']);
        self::assertCount(4, $resumen['movimientos']);
        self::assertSame([], $resumen['facturasSinVencimiento']);
        $factura = $this->em->find(FacturaProveedor::class, $facturaId);
        $factura->setEstadoAsignacion('asignada');
        foreach ($factura->getForecasts() as $forecast) {
            $forecast->setEstadoFr('C');
        }
        $this->em->flush();
        self::assertEquals(850, $service->getResumen(new \DateTimeImmutable('2027-01-31'))['saldoBase']);
    }
}
