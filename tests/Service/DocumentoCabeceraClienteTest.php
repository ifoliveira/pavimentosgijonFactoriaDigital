<?php

namespace App\Tests\Service;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Repository\ClientesRepository;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoRepository;
use App\Service\Documento\DocumentoCabeceraService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DocumentoCabeceraClienteTest extends TestCase
{
    public function testAsignarClienteAlPresupuestoActualizaElProyectoPendiente(): void
    {
        $proyecto = (new Proyecto())->setNombre('Reforma pendiente, calle Corrida 12');
        $presupuesto = (new Documento())
            ->setTipoDocumento('presupuesto')
            ->setProyecto($proyecto);
        $cliente = (new Clientes())->setNombreCl('Ana');

        $documentos = $this->createMock(DocumentoRepository::class);
        $documentos->method('find')->with(27)->willReturn($presupuesto);
        $clientes = $this->createMock(ClientesRepository::class);
        $clientes->method('find')->with(9)->willReturn($cliente);
        $proyectos = $this->createMock(ProyectoRepository::class);
        $proyectos->method('find')->with(12)->willReturn($proyecto);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new DocumentoCabeceraService($documentos, $clientes, $proyectos, $em))->guardarCabecera(27, [
            'clienteId' => 9,
            'proyectoId' => 12,
        ]);

        self::assertSame($cliente, $presupuesto->getCliente());
        self::assertSame($cliente, $proyecto->getCliente());
    }
}
