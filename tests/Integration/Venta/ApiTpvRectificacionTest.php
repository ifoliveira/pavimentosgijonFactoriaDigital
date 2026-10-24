<?php

namespace App\Tests\Integration\Venta;

use App\Controller\ApiTpvController;
use App\Entity\Cestas;
use App\Entity\Documento;
use App\Repository\ProductosRepository;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Venta\RectificacionTicketService;
use App\Service\Venta\VentaDocumentoService;
use App\Service\Venta\VentaLineaInput;
use App\Service\Venta\VentaTpvInput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiTpvRectificacionTest extends TpvDatabaseTestCase
{
    private const VENTA = '550e8400-e29b-41d4-a716-446655440000';
    private const RECT = '550e8400-e29b-41d4-a716-446655440010';
    private const REEMBOLSO = '550e8400-e29b-41d4-a716-446655440020';

    protected function setUp(): void
    {
        parent::setUp();
        $categoria = new \App\Entity\Tiposmovimiento();
        $categoria->setDescripcionTm('Ventas');
        $this->em->persist($categoria);
        $this->em->flush();
        self::getContainer()->set(RectificacionTicketService::class, new RectificacionTicketService($this->registry));
        $controller = new ApiTpvController(
            new VentaDocumentoService($this->registry, new DocumentoCalculatorService()),
            new ProductosRepository($this->registry),
            '{"CAJA-01":"test-key","CAJA-02":"other-key"}'
        );
        $controller->setContainer(self::getContainer());
        self::getContainer()->set(ApiTpvController::class, $controller);
    }

    public function testApiCreaRectificativaExactaYReintentaSinModificarElOriginal(): void
    {
        $original = $this->venta();
        $producto = $this->em->find(\App\Entity\Productos::class, $this->productoId);
        $producto->setPvpPd(999);
        $this->em->flush();
        $respuesta = $this->post($this->ruta(), $this->rectificacion());
        self::assertSame(201, $respuesta->getStatusCode(), $respuesta->getContent());
        $data = json_decode($respuesta->getContent(), true);
        self::assertSame('RT2026-0001', $data['numeroTicket']);
        self::assertSame(-20, $data['total']);
        self::assertSame('pendiente', $data['estadoReembolso']);
        self::assertSame('T2026-0001', $data['ticketOriginal']['numeroTicket']);
        self::assertFalse($data['idempotente']);

        $retry = $this->post($this->ruta(), $this->rectificacion());
        self::assertSame(200, $retry->getStatusCode(), $retry->getContent());
        self::assertTrue(json_decode($retry->getContent(), true)['idempotente']);
        $this->em->clear();
        $original = $this->em->find(Documento::class, $original->getId());
        $rect = $this->em->find(Documento::class, $data['id']);
        self::assertSame('20.00', $original->getTotal());
        self::assertSame('20.00', $original->getTotalCobrado());
        self::assertSame('cobrado', $original->getEstadoCobro());
        self::assertCount(1, $original->getCobros());
        self::assertCount(1, $original->getRectificativas());
        self::assertSame('-16.53', $rect->getBaseImponible());
        self::assertSame('-3.47', $rect->getTotalIva());
        self::assertSame('-2.000', $rect->getLineas()->first()->getCantidad());
        self::assertCount(0, $rect->getCobros());
        self::assertSame(1, $this->contarFilas('documento_cobro'));
        self::assertSame(0, $this->contarFilas('efectivo'));
        self::assertSame(0, $this->contarFilas('forecast'));
        self::assertSame(0, $this->contarFilas('cestas'));
        self::assertSame(0, $this->contarFilas('detallecesta'));
    }

    /** @dataProvider metodosReembolso */
    public function testApiReembolsaSoloTrasConfirmacionYNoDuplica(string $metodo, int $movimientos): void
    {
        $this->venta();
        $this->post($this->ruta(), $this->rectificacion());
        $payload = $this->reembolso($metodo);
        $response = $this->post($this->rutaReembolso(), $payload);
        self::assertSame(201, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true);
        self::assertSame('realizado', $data['estadoReembolso']);
        self::assertSame(self::REEMBOLSO, $data['uuidReembolso']);
        $retry = $this->post($this->rutaReembolso(), $payload);
        self::assertSame(200, $retry->getStatusCode(), $retry->getContent());
        self::assertTrue(json_decode($retry->getContent(), true)['idempotente']);
        $this->em->clear();
        $rect = $this->em->find(Documento::class, $data['id']);
        self::assertSame('devuelto', $rect->getEstadoCobro());
        self::assertSame('-20.00', $rect->getTotalCobrado());
        self::assertSame('-20.00', $rect->getCobros()->first()->getImporteBruto());
        self::assertSame('OPERACION-DATÁFONO-123', $rect->getCobros()->first()->getReferencia());
        self::assertSame(2, $this->contarFilas('documento_cobro'));
        self::assertSame($movimientos, $this->contarFilas('efectivo'));
        self::assertSame($metodo === 'Tarjeta' ? 1 : 0, $this->contarFilas('forecast'));
        if ($metodo === 'Tarjeta') {
            $forecast = $this->em->getRepository(\App\Entity\Forecast::class)->findOneBy([]);
            self::assertSame(-20.0, $forecast->getImporteFr());
            self::assertSame('P', $forecast->getEstadoFr());
            self::assertNull($forecast->getBanco());
            self::assertSame('2026-10-18', $forecast->getFechaFr()->format('Y-m-d'));
            self::assertStringContainsString($rect->getNumeroFormateado(), $forecast->getConceptoFr());
            self::assertStringContainsString($payload['referencia'], $forecast->getConceptoFr());
            $banco = new \App\Entity\Banco();
            $banco->setFechaBn(new \DateTime('2026-10-19'));
            $banco->setImporteBn(-20);
            $banco->setConceptoBn('Devolución tarjeta');
            $this->em->persist($banco);
            $this->em->flush();
            $candidatos = $this->em->getRepository(\App\Entity\Forecast::class)->findCandidatosParaBanco($banco);
            self::assertSame($forecast->getId(), $candidatos[0]['id']);
            (new \App\Service\Forecast\ForecastConciliacionService($this->em))->conciliar($forecast, $banco);
            self::assertSame('C', $forecast->getEstadoFr());
            self::assertSame($banco, $forecast->getBanco());
            self::assertTrue($banco->isConciliado());
        }
        if ($metodo === 'Efectivo') {
            self::assertEquals(-20, $this->em->getConnection()->fetchOne('SELECT importe_ef FROM efectivo'));
        }
    }

    public function metodosReembolso(): iterable
    {
        yield ['Efectivo', 1];
        yield ['Tarjeta', 0];
    }

    public function testSinCategoriaNoConfirmaReembolsoYPermiteReintentar(): void
    {
        $this->venta();
        $this->post($this->ruta(), $this->rectificacion());
        $this->em->getConnection()->executeStatement('DELETE FROM tiposmovimiento');
        $payload = $this->reembolso('Tarjeta');
        $this->assertError($this->post($this->rutaReembolso(), $payload), 409, 'CONFIGURACION_INCOMPLETA');
        self::assertSame(1, $this->contarFilas('documento_cobro'));
        self::assertSame(0, $this->contarFilas('forecast'));
        $rect = $this->em->getRepository(Documento::class)->findOneBy(['tpvUuid' => self::RECT]);
        self::assertSame('pendiente', $rect->getEstadoCobro());
        self::assertNull($rect->getTpvReembolsoUuid());
        $categoria = new \App\Entity\Tiposmovimiento();
        $categoria->setDescripcionTm('Ventas');
        $this->em->persist($categoria);
        $this->em->flush();
        $response = $this->post($this->rutaReembolso(), $payload);
        self::assertSame(201, $response->getStatusCode(), $response->getContent());
        self::assertSame(1, $this->contarFilas('forecast'));
    }

    public function testApiRechazaSegundaRectificacionYUuidConDatosDistintos(): void
    {
        $this->venta();
        $this->post($this->ruta(), $this->rectificacion());
        $data = $this->rectificacion();
        $data['uuid'] = '550e8400-e29b-41d4-a716-446655440011';
        $this->assertError($this->post($this->ruta(), $data), 409, 'TICKET_YA_RECTIFICADO');
        $data = $this->rectificacion();
        $data['motivo'] = 'Otro motivo';
        $this->assertError($this->post($this->ruta(), $data), 409, 'UUID_REUTILIZADO');
        self::assertSame(2, $this->contarFilas('documento'));
    }

    public function testApiNoRectificaCestasNiDocumentosAjenosAlTpv(): void
    {
        $cesta = new Cestas();
        $cesta->setTpvUuid(self::VENTA)->setTpvCaja('CAJA-01');
        $this->em->persist($cesta);
        $this->em->flush();
        $this->assertError($this->post($this->ruta(), $this->rectificacion()), 409, 'TICKET_NO_ADMITIDO');

        $doc = new Documento();
        $doc->setTipoDocumento('factura')->setSerie('F2026')->setNumero(1)->setTpvUuid(self::RECT)->setTpvCaja('CAJA-01');
        $this->em->persist($doc);
        $this->em->flush();
        $this->assertError($this->post($this->ruta(self::RECT), $this->rectificacion()), 409, 'TICKET_NO_ADMITIDO');
        self::assertSame(1, $this->contarFilas('documento'));
    }

    public function testApiPermiteReintentarCuandoLlegaPrimeroLaRectificacion(): void
    {
        $this->assertError($this->post($this->ruta(), $this->rectificacion()), 409, 'VENTA_NO_SINCRONIZADA');
        $this->venta();
        $response = $this->post($this->ruta(), $this->rectificacion());
        self::assertSame(201, $response->getStatusCode(), $response->getContent());
    }

    public function testApiExigeAutenticacionYCajaCorrecta(): void
    {
        $this->venta();
        self::assertSame(401, $this->post($this->ruta(), $this->rectificacion(), 'wrong-key')->getStatusCode());
        self::assertSame(403, $this->post($this->ruta(), $this->rectificacion(), 'other-key')->getStatusCode());
        $data = $this->rectificacion();
        $data['caja'] = 'CAJA-02';
        $this->assertError($this->post($this->ruta(), $data, 'other-key'), 403, 'CAJA_NO_AUTORIZADA');
        self::assertSame(1, $this->contarFilas('documento'));
    }

    /** @dataProvider solicitudesInvalidas */
    public function testApiValidaLaPeticion(array $cambios): void
    {
        $this->venta();
        $this->assertError($this->post($this->ruta(), array_replace($this->rectificacion(), $cambios)), 400, 'DATOS_INVALIDOS');
        self::assertSame(1, $this->contarFilas('documento'));
    }

    public function solicitudesInvalidas(): iterable
    {
        yield [['tipo' => 'parcial']];
        yield [['uuid' => 'incorrecto']];
        yield [['motivo' => '']];
        yield [['fecha' => '2026-02-30T12:00:00+02:00']];
        yield [['fecha' => '2026-10-17T12:00:00+02:00']];
        yield [['lineas' => [['cantidad' => 1]]]];
    }

    public function testApiNoDuplicaReembolsoConOtroUuidNiAceptaCambiosEnElReintento(): void
    {
        $this->venta();
        $this->post($this->ruta(), $this->rectificacion());
        $this->post($this->rutaReembolso(), $this->reembolso());
        $payload = $this->reembolso();
        $payload['uuid'] = '550e8400-e29b-41d4-a716-446655440021';
        $this->assertError($this->post($this->rutaReembolso(), $payload), 409, 'REEMBOLSO_YA_REGISTRADO');
        $payload = $this->reembolso('Tarjeta');
        $this->assertError($this->post($this->rutaReembolso(), $payload), 409, 'REEMBOLSO_YA_REGISTRADO');
        self::assertSame(2, $this->contarFilas('documento_cobro'));
        self::assertSame(1, $this->contarFilas('efectivo'));
    }

    public function testFalloAlGuardarRectificativaRevierteNumeracionYDocumento(): void
    {
        $this->venta();
        $this->em->getEventManager()->addEventListener(['preFlush'], new class {
            public function preFlush(\Doctrine\ORM\Event\PreFlushEventArgs $event): void
            {
                foreach ($event->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof Documento && $entity->isRectificativaTpv()) {
                        throw new \RuntimeException('Fallo simulado de rectificación');
                    }
                }
            }
        });
        try {
            $this->post($this->ruta(), $this->rectificacion());
            self::fail('Debía fallar el guardado.');
        } catch (\RuntimeException $e) {
            self::assertSame('Fallo simulado de rectificación', $e->getMessage());
        }
        self::assertSame(1, $this->contarFilas('documento'));
        self::assertSame(1, $this->contarFilas('documento_cobro'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM serie_documento WHERE codigo = 'RT2026'"));
    }

    /** @dataProvider cambiosProhibidos */
    public function testTicketEmitidoNoSePuedeReescribirNiBorrar(string $cambio): void
    {
        $doc = $this->venta();
        switch ($cambio) {
            case 'documento':
                $doc->setTotal('0.00');
                break;
            case 'ocultar_origen':
                $doc->setTpvUuid(null)->setTipoDocumento('presupuesto')->setTotal('0.00');
                break;
            case 'linea':
                $doc->getLineas()->first()->setPrecioUnitario('0.00');
                break;
            case 'borrar':
                $this->em->remove($doc);
                break;
            case 'borrar_cobro':
                $this->em->remove($doc->getCobros()->first());
                break;
            case 'anadir_linea':
                $linea = new \App\Entity\DocumentoLinea();
                $linea->setDescripcion('Línea extra')->setTipoLinea('producto');
                $doc->addLinea($linea);
                break;
        }
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->em->flush();
    }

    public function cambiosProhibidos(): iterable
    {
        foreach (['documento', 'ocultar_origen', 'linea', 'borrar', 'borrar_cobro', 'anadir_linea'] as $cambio) {
            yield [$cambio];
        }
    }

    public function testProteccionNoAfectaDocumentosQueNoProcedenDelTpv(): void
    {
        $doc = new Documento();
        $doc->setTipoDocumento('ticket')->setSerie('T2026')->setNumero(99);
        $this->em->persist($doc);
        $this->em->flush();
        $doc->setTotal('5.00');
        $this->em->flush();
        self::assertSame('5.00', $doc->getTotal());
        $this->em->remove($doc);
        $this->em->flush();
        self::assertSame(0, $this->contarFilas('documento'));
    }

    /** @dataProvider operacionesConcurrentes */
    public function testPeticionesConcurrentesNoDuplicanRectificacionNiReembolso(bool $reembolso, bool $mismoUuid): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('Requiere pcntl.');
        }
        $this->venta();
        if ($reembolso) {
            self::assertSame(201, $this->post($this->ruta(), $this->rectificacion())->getStatusCode());
        }
        $this->em->getConnection()->close();
        $this->adminConnection->close();
        $start = tempnam(sys_get_temp_dir(), 'tpv_rect_start_');
        unlink($start);
        $files = [];
        $pids = [];
        try {
            for ($i = 0; $i < 2; ++$i) {
                $files[$i] = tempnam(sys_get_temp_dir(), 'tpv_rect_result_');
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException('No se pudo iniciar el proceso de prueba.');
                }
                if ($pid === 0) {
                    while (!is_file($start)) {
                        usleep(1000);
                    }
                    try {
                        $payload = $reembolso ? $this->reembolso() : $this->rectificacion();
                        if (!$mismoUuid && $i === 1) {
                            $payload['uuid'] = '550e8400-e29b-41d4-a716-446655440099';
                        }
                        $response = $this->post($reembolso ? $this->rutaReembolso() : $this->ruta(), $payload);
                        file_put_contents($files[$i], json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]));
                        exit(0);
                    } catch (\Throwable $e) {
                        file_put_contents($files[$i], $e->getMessage());
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
            $results = array_map(fn ($file) => json_decode(file_get_contents($file), true), $files);
            $statuses = array_column($results, 'status');
            sort($statuses);
            self::assertSame($mismoUuid ? [200, 201] : [201, 409], $statuses, json_encode($results));
            self::assertSame(2, $this->contarFilas('documento'));
            self::assertSame($reembolso ? 2 : 1, $this->contarFilas('documento_cobro'));
            self::assertSame($reembolso ? 1 : 0, $this->contarFilas('efectivo'));
            self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT ultimo_numero FROM serie_documento WHERE codigo = 'RT2026'"));
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

    public function operacionesConcurrentes(): iterable
    {
        yield 'rectificación repetida' => [false, true];
        yield 'dos rectificaciones distintas de una venta' => [false, false];
        yield 'reembolso repetido' => [true, true];
        yield 'dos reembolsos distintos' => [true, false];
    }

    private function venta(): Documento
    {
        return (new VentaDocumentoService($this->registry, new DocumentoCalculatorService()))->crearVentaTpv(
            new VentaTpvInput(self::VENTA, new \DateTimeImmutable('2026-10-18T12:30:00+02:00'), 'CAJA-01', 'Tarjeta',
                [new VentaLineaInput($this->productoId, 2, 10, 12.5, null, 0, 'Producto', 21)], 'LOCAL-42', 20)
        )->venta;
    }

    private function rectificacion(): array
    {
        return ['uuid' => self::RECT, 'fecha' => '2026-10-18T13:00:00+02:00', 'caja' => 'CAJA-01',
            'tipo' => 'total', 'motivo' => 'Devolución de la compra', 'numeroTicketLocal' => 'DEV-42'];
    }

    private function reembolso(string $metodo = 'Efectivo'): array
    {
        return ['uuid' => self::REEMBOLSO, 'fecha' => '2026-10-18T13:15:00+02:00', 'caja' => 'CAJA-01',
            'formaPago' => $metodo, 'referencia' => 'OPERACION-DATÁFONO-123'];
    }

    private function ruta(string $uuid = self::VENTA): string
    {
        return '/api/tpv/ventas/' . $uuid . '/rectificaciones';
    }

    private function rutaReembolso(): string
    {
        return $this->ruta() . '/' . self::RECT . '/reembolso';
    }

    private function post(string $path, array $payload, string $key = 'test-key'): Response
    {
        $request = Request::create($path, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_TPV_API_KEY' => $key,
        ], json_encode($payload));
        return self::$kernel->handle($request, \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST, false);
    }

    private function assertError(Response $response, int $status, string $codigo): void
    {
        self::assertSame($status, $response->getStatusCode(), $response->getContent());
        self::assertSame($codigo, json_decode($response->getContent(), true)['codigo']);
    }

    private function contarFilas(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
