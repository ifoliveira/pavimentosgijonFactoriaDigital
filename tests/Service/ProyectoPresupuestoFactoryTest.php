<?php

namespace App\Tests\Service;

use App\Entity\Proyecto;
use App\Service\Documento\DocumentoAccionesService;
use App\Service\Documento\DocumentoCrearService;
use App\Service\Proyecto\ProyectoPresupuestoFactory;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ProyectoPresupuestoFactoryTest extends TestCase
{
    public function testCreaElPresupuestoInicialAunqueElProyectoNoTengaCliente(): void
    {
        $proyecto = (new Proyecto())
            ->setNombre('Reforma pendiente en calle Corrida 12')
            ->setNotas('La persona interesada aún no ha facilitado sus datos.')
            ->setTipoObra('mampara')
            ->setFechaInicio(new \DateTimeImmutable('2026-11-14'));

        $em = $this->createMock(EntityManager::class);
        $em->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $operacion): mixed => $operacion());
        $em->expects(self::once())->method('persist')->with($proyecto);
        $em->expects(self::exactly(2))->method('flush');

        $documentos = $this->createMock(DocumentoCrearService::class);
        $presupuesto = new \App\Entity\Documento();
        $documentos->expects(self::once())
            ->method('crearDocumento')
            ->with('presupuesto', null)
            ->willReturn($presupuesto);

        $resultado = (new ProyectoPresupuestoFactory($em, $documentos))
            ->crearDesdeProyecto($proyecto, $proyecto->getTipoObra());

        self::assertSame($proyecto, $resultado['proyecto']);
        self::assertSame($presupuesto, $resultado['presupuesto']);
        self::assertSame($proyecto, $presupuesto->getProyecto());
        self::assertSame('Reforma pendiente en calle Corrida 12', $proyecto->getNombre());
        self::assertSame('La persona interesada aún no ha facilitado sus datos.', $proyecto->getNotas());
        self::assertSame('Mampara', $proyecto->getTipoObraEtiqueta());
    }

    public function testExigeNombreCuandoNoHayCliente(): void
    {
        $em = $this->createMock(EntityManager::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $operacion): mixed => $operacion());

        $documentos = $this->createMock(DocumentoCrearService::class);
        $documentos->expects(self::never())->method('crearDocumento');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Selecciona un cliente o escribe un nombre descriptivo');

        (new ProyectoPresupuestoFactory($em, $documentos))->crearDesdeProyecto(new Proyecto());
    }

    public function testLaValidacionDelFormularioExplicaComoIdentificarUnProyectoSinCliente(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $errores = $validator->validate((new Proyecto())->setNombre('   '));

        self::assertCount(1, $errores);
        self::assertSame('nombre', $errores[0]->getPropertyPath());
        self::assertStringContainsString('Selecciona un cliente o escribe un nombre descriptivo', $errores[0]->getMessage());

        self::assertCount(0, $validator->validate(
            (new Proyecto())->setNombre('Pendiente de identificar, calle Corrida 12')
        ));
    }

    public function testUnPresupuestoSinClienteSePuedeEditarPeroNoAceptar(): void
    {
        $presupuesto = (new \App\Entity\Documento())
            ->setTipoDocumento('presupuesto')
            ->setEstadoComercial('entregado');

        $acciones = (new DocumentoAccionesService())->getAccionesDisponibles($presupuesto);

        self::assertFalse($acciones['puedeAceptar']);
        self::assertTrue($acciones['requiereClienteParaAceptar']);
        self::assertTrue($acciones['puedeEditarCabecera']);
        self::assertTrue($acciones['puedeGenerarPdf']);
    }
}
