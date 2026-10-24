<?php

namespace App\Tests\Integration\Venta;

use App\Entity\Cestas;
use App\Entity\Documento;
use App\Entity\Productos;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Venta\VentaDocumentoService;
use App\Service\Venta\VentaLineaInput;
use App\Service\Venta\VentaTpvInput;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Opt-in: TPV_DATABASE_TESTS=1. Crea y elimina una base codex_tpv_test_* por prueba.
 * Nunca escribe en la base configurada por la aplicación.
 */
final class VentaDocumentoServiceTest extends TpvDatabaseTestCase
{
    public function testPersisteDocumentoLineasCobroYCajaSinCestasYReintenta(): void
    {
        $service = $this->service();
        $resultado = $service->crearVentaTpv($this->input('Efectivo'));
        $id = $resultado->venta->getId();
        self::assertFalse($resultado->idempotente);
        self::assertSame('T2026-0001', $resultado->toArray()['numeroTicket']);
        $this->em->clear();

        $doc = $this->em->find(Documento::class, $id);
        self::assertSame('20.00', $doc->getTotal());
        self::assertSame('16.53', $doc->getBaseImponible());
        self::assertSame('3.47', $doc->getTotalIva());
        self::assertSame('cobrado', $doc->getEstadoCobro());
        self::assertSame('10.00', $doc->getLineas()->first()->getPrecioUnitarioConIva());
        self::assertSame('20.00', $doc->getCobros()->first()->getImporteNeto());
        self::assertSame('efectivo', $doc->getCobros()->first()->getMetodo());
        self::assertSame('2026-10-18 12:30:00', $doc->getTpvFecha()->format('Y-m-d H:i:s'));

        $retry = $service->crearVentaTpv($this->input('Efectivo'));
        self::assertTrue($retry->idempotente);
        self::assertSame($id, $retry->venta->getId());
        foreach (['documento' => 1, 'documento_linea' => 1, 'documento_cobro' => 1, 'efectivo' => 1, 'cestas' => 0, 'detallecesta' => 0, 'pagos' => 0] as $table => $count) {
            self::assertSame($count, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table));
        }
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT ultimo_numero FROM serie_documento'));
    }

    public function testReintentoAnteriorAlCambioNoGeneraDocumento(): void
    {
        $cesta = new Cestas();
        $cesta->setTpvUuid($this->input()->uuid)->setTpvCaja('CAJA-01')->setNumticketCs('antiguo.pdf')->setEstadoCs(2)->setImporteTotCs(20);
        $this->em->persist($cesta);
        $this->em->flush();

        $resultado = $this->service()->crearVentaTpv($this->input());
        self::assertTrue($resultado->idempotente);
        self::assertSame('antiguo.pdf', $resultado->toArray()['numeroTicket']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM documento'));
    }

    public function testTotalInvalidoNoPersisteNada(): void
    {
        try {
            $this->service()->crearVentaTpv($this->input(total: 999));
            self::fail('Debía rechazar el total.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('total informado', $e->getMessage());
        }
        foreach (['documento', 'documento_linea', 'documento_cobro', 'serie_documento', 'efectivo'] as $table) {
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table));
        }
    }

    public function testNumeracionCompartidaYTarjetaNoGeneraEfectivo(): void
    {
        $service = $this->service();
        $service->crearVentaTpv($this->input());
        $segunda = $service->crearVentaTpv($this->input(uuid: '550e8400-e29b-41d4-a716-446655440001'));
        self::assertSame('T2026-0002', $segunda->toArray()['numeroTicket']);
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM documento_cobro'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM efectivo'));
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM forecast'));
        $forecast = $this->em->getRepository(\App\Entity\Forecast::class)->findOneBy([], ['id' => 'ASC']);
        self::assertSame(20.0, $forecast->getImporteFr());
        self::assertSame('P', $forecast->getEstadoFr());
        self::assertSame('TPV', $forecast->getOrigenFr());
        self::assertStringContainsString('T2026-0001', $forecast->getConceptoFr());
    }

    public function testFalloDePersistenciaRevierteTambienElContador(): void
    {
        $listener = new class {
            public function preFlush(\Doctrine\ORM\Event\PreFlushEventArgs $event): void
            {
                foreach ($event->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof Documento) {
                        throw new \RuntimeException('Fallo simulado antes de guardar el documento.');
                    }
                }
            }
        };
        $this->em->getEventManager()->addEventListener(['preFlush'], $listener);
        try {
            $this->service()->crearVentaTpv($this->input('Efectivo'));
            self::fail('Debía fallar el guardado.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Fallo simulado', $e->getMessage());
        }
        foreach (['documento', 'documento_linea', 'documento_cobro', 'serie_documento', 'efectivo'] as $table) {
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table));
        }
    }

    /** @dataProvider ventasConcurrentes */
    public function testVentasSimultaneasNoDuplicanUuidNiNumeracion(bool $mismoUuid): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('Requiere pcntl para comprobar concurrencia real.');
        }
        // Ningún socket se comparte entre procesos.
        $this->em->getConnection()->close();
        $this->adminConnection->close();
        $start = tempnam(sys_get_temp_dir(), 'tpv_start_');
        unlink($start);
        $files = [];
        $pids = [];
        try {
            for ($i = 0; $i < 2; ++$i) {
                $files[$i] = tempnam(sys_get_temp_dir(), 'tpv_result_');
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException('No se pudo crear el proceso de prueba.');
                }
                if ($pid === 0) {
                    while (!is_file($start)) {
                        usleep(1000);
                    }
                    try {
                        $uuid = $mismoUuid || $i === 0
                            ? '550e8400-e29b-41d4-a716-446655440000'
                            : '550e8400-e29b-41d4-a716-446655440001';
                        $result = $this->service()->crearVentaTpv($this->input(uuid: $uuid));
                        file_put_contents($files[$i], json_encode($result->toArray()));
                        exit(0);
                    } catch (\Throwable $e) {
                        file_put_contents($files[$i], json_encode(['error' => $e->getMessage()]));
                        exit(1);
                    }
                }
                $pids[] = $pid;
            }
            touch($start);
            foreach ($pids as $i => $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status), file_get_contents($files[$i]));
            }
            $resultados = array_map(fn ($file) => json_decode(file_get_contents($file), true), $files);
            self::assertSame($mismoUuid ? 1 : 2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM documento'));
            self::assertSame($mismoUuid ? 1 : 2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM documento_cobro'));
            self::assertSame($mismoUuid ? 1 : 2, (int) $this->em->getConnection()->fetchOne('SELECT ultimo_numero FROM serie_documento'));
            self::assertSame($mismoUuid ? 1 : 2, count(array_unique(array_column($resultados, 'numeroTicket'))));
            if ($mismoUuid) {
                self::assertSame(1, count(array_filter(array_column($resultados, 'idempotente'))));
            }
        } finally {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }
            foreach ($files as $file) {
                unlink($file);
            }
            if (is_file($start)) {
                unlink($start);
            }
        }
    }

    public function ventasConcurrentes(): iterable
    {
        yield 'mismo UUID' => [true];
        yield 'distintos UUID al abrir la serie anual' => [false];
    }

    private function service(): VentaDocumentoService
    {
        return new VentaDocumentoService($this->registry, new DocumentoCalculatorService());
    }

    private function input(string $pago = 'Tarjeta', float $total = 20, string $uuid = '550e8400-e29b-41d4-a716-446655440000'): VentaTpvInput
    {
        return new VentaTpvInput($uuid, new \DateTimeImmutable('2026-10-18T12:30:00+02:00'), 'CAJA-01', $pago,
            [new VentaLineaInput($this->productoId, 2, 10, 12.5, null, 0, 'Producto', 21)], 'LOCAL-42', $total);
    }
}
