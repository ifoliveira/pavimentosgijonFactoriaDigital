<?php

namespace App\Tests\Templates;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class FacturaFinalCobroPdfTest extends KernelTestCase
{
    /** @dataProvider saldos */
    public function testPdfSoloDescuentaCobrosDeLaFactura(
        string $cobradoFactura,
        string $pendienteEsperado,
        bool $conProyecto
    ): void {
        self::bootKernel();
        $factura = (new Documento())->setTipoDocumento('factura')
            ->setSerie('F2026')->setNumero(2)->setTotal('2000.00')
            ->setTotalCobrado($cobradoFactura);

        if ($conProyecto) {
            $proyecto = (new Proyecto())->setTotalPresupuestado('3000.00')
                ->setTotalCobrado(bcadd('1000.00', $cobradoFactura, 2));
            $anticipo = (new Documento())->setTipoDocumento('factura')->setTotal('1000.00');
            $proyecto->addCobro((new ProyectoCobro())
                ->setImporteBruto('1000.00')->setFacturaAnticipo($anticipo));
            $proyecto->addDocumento($factura);
        }

        $html = self::getContainer()->get(Environment::class)->render('documento/pdf-clasico.html.twig', [
            'documento' => $factura,
            'iva' => '21 %',
            'logoPath' => '',
        ]);
        $texto = preg_replace('/\s+/u', ' ', strip_tags($html));

        if ($pendienteEsperado === '0,00') {
            self::assertStringContainsString('Factura pagada', $texto);
            self::assertStringNotContainsString('Pendiente de pago', $texto);
        } else {
            self::assertStringContainsString('Pendiente de pago ' . $pendienteEsperado . ' €', $texto);
            self::assertStringContainsString('Total cobrado ' . number_format((float) $cobradoFactura, 2, ',', '.') . ' €', $texto);
            self::assertStringNotContainsString('Factura pagada', $texto);
        }
    }

    /** @dataProvider pagosResumen */
    public function testResumenMuestraCosteCompletoSinDescontarDosVeces(string $pago, string $pendiente): void
    {
        self::bootKernel();
        $factura = (new Documento())->setTipoDocumento('factura')->setSerie('F2026')->setNumero(2)
            ->setTotal('2000.00')->setTotalCobrado($pago);
        $factura->addLinea((new DocumentoLinea())->setOrigenLinea('factura_final')
            ->setSubtotal('2479.34')->setTotalIva('520.66'));
        $factura->addLinea((new DocumentoLinea())->setOrigenLinea('descuento_anticipo')
            ->setSubtotal('-826.45')->setTotalIva('-173.55'));
        // Los cambios posteriores del proyecto no alteran el coste documentado.
        $proyecto = (new Proyecto())->setTotalPresupuestado('9000.00')->setTotalCobrado('8000.00');
        $proyecto->addDocumento($factura);

        self::assertSame([
            'totalObra' => '3000.00',
            'anticipos' => '1000.00',
            'totalFactura' => '2000.00',
            'cobradoFactura' => $pago,
            'pendiente' => $pendiente,
        ], $factura->getResumenFacturaFinal());
        $html = self::getContainer()->get(Environment::class)->render('documento/pdf-clasico.html.twig', [
            'documento' => $factura, 'iva' => '21 %', 'logoPath' => '',
        ]);
        $texto = preg_replace('/\s+/u', ' ', strip_tags($html));
        self::assertStringContainsString('Precio total de la reforma, IVA incluido 3.000,00 €', $texto);
        self::assertStringContainsString('Anticipos facturados y abonados −1.000,00 €', $texto);
        self::assertStringContainsString('Total de esta factura final 2.000,00 €', $texto);
        self::assertStringContainsString('Pendiente de pago '.number_format((float) $pendiente, 2, ',', '.').' €', $texto);
    }

    public function testResumenFiscalUnicoConReferenciasYCabeEnUnaPagina(): void
    {
        self::bootKernel();
        $factura = (new Documento())->setTipoDocumento('factura')->setSerie('F2026')->setNumero(42)
            ->setBaseImponible('1818.18')->setTotalIva('181.82')->setTotal('2000.00')->setTotalCobrado('0.00');
        $obra = (new DocumentoLinea())->setOrigenLinea('factura_final')->setTipoLinea('servicio')
            ->setDescripcion('Materiales y trabajos de reforma')->setCantidad('1.000')->setPrecioUnitario('2791.80')
            ->setSubtotal('2791.80')->setTotalIva('279.18')->setTipoIva('10.00');
        $factura->addLinea($obra);
        foreach ([['F2026-0001', '-500.00', '-50.00'], ['F2026-0002', '-473.62', '-47.36']] as [$numero, $base, $iva]) {
            $factura->addLinea((new DocumentoLinea())->setOrigenLinea('descuento_anticipo')->setTipoLinea('servicio')
                ->setDescripcion('Descuento anticipo '.$numero.' — importe factura: anticipo abonado')
                ->setSubtotal($base)->setTotalIva($iva));
        }
        $html = self::getContainer()->get(Environment::class)->render('documento/pdf-clasico.html.twig', [
            'documento' => $factura, 'iva' => '10 %', 'logoPath' => '',
        ]);
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        self::assertCount(1, $crawler->filter('.lineas-table tbody tr'));
        self::assertStringContainsString('Materiales y trabajos de reforma', $crawler->filter('.lineas-table')->text());
        self::assertStringNotContainsString('Descuento anticipo', $html);
        self::assertCount(1, $crawler->filter('.resumen-economico-fiscal'));
        self::assertCount(0, $crawler->filter('.totales-section, .cobros-section'));
        self::assertStringNotContainsString('Situación de cobro', $html);
        $filas = $crawler->filter('.resumen-economico-fiscal tr')->each(
            static fn ($fila): string => preg_replace('/\s+/u', ' ', $fila->text())
        );
        self::assertSame([
            'Precio total de la reforma, IVA incluido 3.070,98 €',
            'Anticipos facturados y abonados Facturas: F2026-0001, F2026-0002 −1.070,98 €',
            'Base imponible de esta factura 1.818,18 €',
            'IVA 10 % de esta factura 181,82 €',
            'Total de esta factura final 2.000,00 €',
            'Pagos posteriores imputados a esta factura 0,00 €',
            'Pendiente de pago 2.000,00 €',
        ], $filas);
        self::assertCount(3, $factura->getLineas());
        self::assertSame('1818.18', $factura->getBaseImponible());
        self::assertSame('181.82', $factura->getTotalIva());
        self::assertSame('2000.00', $factura->getTotal());

        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        self::assertSame(1, $pdf->getCanvas()->get_page_count());
    }

    public function pagosResumen(): iterable
    {
        yield ['0.00', '2000.00'];
        yield ['500.00', '1500.00'];
        yield ['2000.00', '0.00'];
    }

    public function testResumenSinAnticiposYConVariosTiposDeIva(): void
    {
        $factura = (new Documento())->setTipoDocumento('factura')->setTotal('2310.00');
        self::assertNull($factura->getResumenFacturaFinal());
        $factura->addLinea((new DocumentoLinea())->setOrigenLinea('factura_final')
            ->setSubtotal('2000.00')->setTotalIva('310.00'));
        self::assertSame('2310.00', $factura->getResumenFacturaFinal()['totalObra']);
        self::assertSame('0.00', $factura->getResumenFacturaFinal()['anticipos']);
        $factura->addLinea((new DocumentoLinea())->setOrigenLinea('descuento_anticipo')
            ->setSubtotal('-100.00')->setTotalIva('-21.00'));
        $factura->addLinea((new DocumentoLinea())->setOrigenLinea('descuento_anticipo')
            ->setSubtotal('-100.00')->setTotalIva('-10.00'));
        $factura->setTotal('2079.00');
        self::assertSame('2310.00', $factura->getResumenFacturaFinal()['totalObra']);
        self::assertSame('231.00', $factura->getResumenFacturaFinal()['anticipos']);
    }

    public function saldos(): iterable
    {
        yield 'anticipo ya descontado' => ['0.00', '2.000,00', true];
        yield 'pago posterior de 500' => ['500.00', '1.500,00', true];
        yield 'factura final pagada' => ['2000.00', '0,00', true];
        yield 'factura sin proyecto ni cobros' => ['0.00', '2.000,00', false];
    }
}
