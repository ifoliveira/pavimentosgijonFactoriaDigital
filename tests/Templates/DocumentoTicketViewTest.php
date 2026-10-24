<?php

namespace App\Tests\Templates;

use App\Entity\Documento;
use App\Service\Documento\DocumentoAccionesService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

final class DocumentoTicketViewTest extends KernelTestCase
{
    /** @dataProvider tiposTicket */
    public function testTicketSinClienteNiProyectoSePuedeConsultar(bool $rectificativa): void
    {
        self::bootKernel();
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        ));
        self::getContainer()->get('request_stack')->push($request);
        $twig = self::getContainer()->get(Environment::class);
        $twig->setLoader(new ChainLoader([
            new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']),
            $twig->getLoader(),
        ]));
        $doc = new Documento();
        (new \ReflectionProperty(Documento::class, 'id'))->setValue($doc, 123);
        $doc->setSerie('T2026')->setNumero(42)->setTipoDocumento('ticket')->setEstadoCobro('cobrado');
        $doc->setTpvUuid('550e8400-e29b-41d4-a716-446655440000')->setTpvCaja('CAJA-01')->setTpvNumeroTicketLocal('LOCAL-42');
        if ($rectificativa) {
            $original = new Documento();
            (new \ReflectionProperty(Documento::class, 'id'))->setValue($original, 122);
            $original->setSerie('T2026')->setNumero(41)->setTipoDocumento('ticket');
            $original->setFechaEmision(new \DateTime('2026-10-18'));
            $doc->setSerie('RT2026')->setTipoDocumento('ticket_rectificativo')->setEstadoCobro('pendiente');
            $doc->setDocumentoRectificado($original)->setMotivoRectificacion('Devolución completa');
        }
        $cobro = new \App\Entity\DocumentoCobro();
        (new \ReflectionProperty(\App\Entity\DocumentoCobro::class, 'id'))->setValue($cobro, 1);
        $cobro->setMetodo('tarjeta');
        $doc->addCobro($cobro);
        $linea = (new \App\Entity\DocumentoLinea())
            ->setTipoLinea('producto')
            ->setDescripcion('Pavimento porcelánico')
            ->setCantidad('2.000')
            ->setPrecioUnitario('99.00')
            ->setSubtotal('198.00');
        $doc->addLinea($linea);

        $html = $twig->render('documento/show.html.twig', [
            'documento' => $doc,
            'acciones' => (new DocumentoAccionesService())->getAccionesDisponibles($doc),
            'ivaFacturaEstado' => [],
            'estimacionMaterialesFactura' => [],
            'editarCabecera' => false,
            'clientes' => [],
            'proyectos' => [],
            'tiposManoObra' => [],
            'textosManoObra' => [],
            'seleccionadosManoObra' => [],
            'textosManuales' => [],
            'productosBuscador' => [],
        ]);
        self::assertStringContainsString('T2026-0042', $html);
        self::assertStringContainsString('LOCAL-42', $html);
        self::assertStringContainsString('Volver a documentos', $html);
        self::assertStringNotContainsString('Volver a proyecto', $html);
        self::assertStringNotContainsString('title="Borrar cobro"', $html);
        self::assertStringContainsString('PDF sin desglose', $html);
        $pdfSinDesglose = $twig->render('documento/pdf-clasico.html.twig', [
            'documento' => $doc,
            'iva' => '21 %',
            'logoPath' => '',
            'mostrarImportesLineas' => false,
        ]);
        self::assertStringContainsString('Pavimento porcelánico', $pdfSinDesglose);
        self::assertStringNotContainsString('>Precio<', $pdfSinDesglose);
        self::assertStringNotContainsString('99,00 €', $pdfSinDesglose);
        self::assertStringContainsString('>Total<', $pdfSinDesglose);
        if ($rectificativa) {
            self::assertStringContainsString('pendiente de confirmación del TPV', $html);
            $pdf = $twig->render('documento/pdf-clasico.html.twig', ['documento' => $doc, 'iva' => '21 %', 'logoPath' => '']);
            self::assertStringContainsString('FACTURA SIMPLIFICADA RECTIFICATIVA', $pdf);
            self::assertStringContainsString('T2026-0041', $pdf);
            self::assertStringContainsString('18/10/2026', $pdf);
            self::assertStringContainsString('Devolución completa', $pdf);
        }
    }
    public function tiposTicket(): iterable
    {
        yield [false];
        yield [true];
    }
}
