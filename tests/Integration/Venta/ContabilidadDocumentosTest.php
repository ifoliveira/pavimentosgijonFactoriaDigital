<?php

namespace App\Tests\Integration\Venta;

use App\Entity\Documento;
use App\Entity\DocumentoCobro;
use App\Repository\DocumentoRepository;
use App\Repository\DocumentoCobroRepository;

final class ContabilidadDocumentosTest extends TpvDatabaseTestCase
{
    private int $numero = 0;

    public function testCobrosPorFechaYPendienteSinDuplicarPresupuestos(): void
    {
        $documentos = new DocumentoRepository($this->registry);
        $cobros = new DocumentoCobroRepository($this->registry);
        self::assertEquals(0, $documentos->getTotalPendienteCobro());
        self::assertEquals(0, $cobros->getTotalCobrado());

        // Factura antigua, con dos cobros actuales y uno anterior.
        $factura = $this->documento('factura', '1000.00', '500.00');
        $this->cobro($factura, '2025-12-31', '100.00');
        $this->cobro($factura, '2026-01-01', '150.00');
        $this->cobro($factura, '2026-10-20', '250.00');
        $this->documento('presupuesto', '1000.00', '0.00');
        $ticket = $this->documento('ticket', '200.00', '200.00');
        $this->cobro($ticket, '2026-10-20', '200.00');
        $rectificativo = $this->documento('ticket_rectificativo', '-50.00', '-50.00');
        $this->cobro($rectificativo, '2026-10-20', '-50.00');
        // Un exceso cobrado no cancela la deuda de otro cliente.
        $exceso = $this->documento('factura', '100.00', '120.00');
        $this->cobro($exceso, '2027-01-01', '120.00');
        $this->em->flush();

        self::assertEquals(550, $cobros->getTotalCobrado(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-01-01')));
        self::assertEquals(770, $cobros->getTotalCobrado());
        self::assertEquals(500, $documentos->getTotalPendienteCobro());
    }

    public function testProyectoConFacturaTicketAnticipoYCobroDirecto(): void
    {
        $schema = (new \Doctrine\ORM\Tools\SchemaTool($this->em))->getSchemaFromMetadata([
            $this->em->getClassMetadata(\App\Entity\Proyecto::class),
            $this->em->getClassMetadata(\App\Entity\ProyectoCobro::class),
        ]);
        foreach ($schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $key) {
                $table->removeForeignKey($key->getName());
            }
        }
        foreach ($schema->toSql($this->em->getConnection()->getDatabasePlatform()) as $sql) {
            $this->em->getConnection()->executeStatement($sql);
        }
        $proyecto = (new \App\Entity\Proyecto())->setNombre('Obra con pagos parciales');
        $proyecto->setCliente($this->em->getReference(\App\Entity\Clientes::class, 1));
        $proyecto->setTotalFacturado('1200.00');
        $this->em->persist($proyecto);
        $this->documento('factura', '1000.00', '100.00')->setProyecto($proyecto);
        $this->documento('ticket', '200.00', '0.00')->setProyecto($proyecto);
        $this->documento('presupuesto', '1200.00', '0.00')->setProyecto($proyecto);
        foreach ([['2025-12-31', '200.00'], ['2026-01-01', '300.00']] as [$fecha, $importe]) {
            $cobro = (new \App\Entity\ProyectoCobro())->setProyecto($proyecto);
            $cobro->setFecha(new \DateTime($fecha))->setMetodo('transferencia');
            $cobro->setImporteBruto($importe)->setImporteNeto($importe);
            $this->em->persist($cobro);
        }
        $this->em->flush();
        $proyectos = new \App\Repository\ProyectoRepository($this->registry);
        $cobros = new \App\Repository\ProyectoCobroRepository($this->registry);
        self::assertEquals(600, $proyectos->getTotalPendienteCobro());
        self::assertEquals(0, (new DocumentoRepository($this->registry))->getTotalPendienteCobro());
        self::assertEquals(500, $cobros->getTotalCobrado());
        self::assertEquals(300, $cobros->getTotalCobrado(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-01-01')));
        // Cobrado de más: nunca reduce la deuda de otros proyectos.
        $proyecto->setTotalFacturado('5000.00');
        $this->documento('ticket_rectificativo', '-700.00', '0.00')->setProyecto($proyecto);
        $this->em->flush();
        self::assertEquals(0, $proyectos->getTotalPendienteCobro());
    }

    public function testIvaTrimestralIncluyeFacturasAceptadasSinDependerDelCobro(): void
    {
        $repo = new DocumentoRepository($this->registry);
        $desde = new \DateTimeImmutable('2026-01-01');
        $hasta = new \DateTimeImmutable('2027-01-01');
        self::assertSame([], $repo->getResumenIvaTrimestral($desde, $hasta));
        $crear = function (string $tipo, string $estado, string $fecha, string $base = '100.00', string $iva = '21.00'): Documento {
            $doc = $this->documento($tipo, bcadd($base, $iva, 2), '0.00');
            $doc->setBaseImponible($base)->setTotalIva($iva);
            $doc->setEstadoComercial($estado)->setFechaEmision(new \DateTime($fecha));
            return $doc;
        };
        $crear('ticket', 'borrador', '2026-01-01');
        // Aceptada en otro trimestre y sin cobrar: cuenta por emisión.
        $crear('factura', 'aceptado', '2026-03-31')->setFechaAceptacion(new \DateTime('2026-04-02'));
        $facturaProyecto = $crear('factura', 'borrador', '2026-04-01', '200.00', '20.00');
        $crear('presupuesto', 'convertido', '2026-04-01')->setFacturaVinculada($facturaProyecto);
        $crear('presupuesto', 'aplicado', '2026-04-01')->setFacturaVinculada($facturaProyecto);
        $crear('ticket_rectificativo', 'aceptado', '2026-06-30', '-50.00', '-10.50');
        $crear('factura', 'aceptado', '2026-12-31')->setTotalCobrado('121.00');
        // No sumamos borradores independientes, rechazadas ni presupuestos.
        $crear('factura', 'borrador', '2026-07-01');
        $crear('factura', 'entregado', '2026-07-01');
        $crear('factura', 'rechazado', '2026-07-01');
        $crear('presupuesto', 'aceptado', '2026-07-01');
        $crear('factura', 'aceptado', '2025-12-31');
        $crear('factura', 'aceptado', '2027-01-01');
        $this->em->flush();
        self::assertSame([
            ['trimestre' => 'T1', 'baseImponible' => '200.00', 'ivaRepercutido' => '42.00', 'total' => '242.00'],
            ['trimestre' => 'T2', 'baseImponible' => '150.00', 'ivaRepercutido' => '9.50', 'total' => '159.50'],
            ['trimestre' => 'T4', 'baseImponible' => '100.00', 'ivaRepercutido' => '21.00', 'total' => '121.00'],
        ], $repo->getResumenIvaTrimestral($desde, $hasta));
    }

    private function documento(string $tipo, string $total, string $cobrado): Documento
    {
        $doc = (new Documento())->setTipoDocumento($tipo)->setTotal($total)->setTotalCobrado($cobrado);
        $doc->setSerie('TEST')->setNumero(++$this->numero);
        $doc->setFechaEmision(new \DateTime('2025-01-01'));
        $this->em->persist($doc);
        return $doc;
    }

    private function cobro(Documento $documento, string $fecha, string $importe): void
    {
        $cobro = (new DocumentoCobro())->setDocumento($documento)->setFecha(new \DateTime($fecha));
        $cobro->setMetodo('tarjeta')->setImporteBruto($importe)->setImporteNeto($importe);
        $this->em->persist($cobro);
    }
}
