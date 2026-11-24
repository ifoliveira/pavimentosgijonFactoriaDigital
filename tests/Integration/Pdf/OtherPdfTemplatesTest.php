<?php

namespace App\Tests\Integration\Pdf;

use App\Entity\Documento;
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/** Smoke coverage of the other PDF templates, without database or network access. */
final class OtherPdfTemplatesTest extends TestCase
{
    /** @dataProvider templates */
    public function testPdfTemplateRenders(string $template): void
    {
        $root = dirname(__DIR__, 3);
        $twig = new Environment(new FilesystemLoader($root . '/templates'));
        $twig->addExtension(new IntlExtension());
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));
        $document = (new Documento())->setTipoDocumento('factura')->setSerie('TEST')->setNumero(1);
        $project = new class {
            public function getCesta($id): array
            {
                return ['getdetallecesta' => [], 'getdescuentoCs' => 0];
            }
        };
        $html = $twig->render($template, [
            'ticket' => $document, 'tickets' => [$document], 'facturas' => [],
            'anio' => 2026, 'trimestre' => 1, 'esAnual' => false, 'filtroRecargo' => null,
            'fechaDesde' => new \DateTimeImmutable('2026-01-01'),
            'fechaHasta' => new \DateTimeImmutable('2026-03-31'),
            'resumen' => ['numeroTickets' => 1, 'numeroFacturas' => 1, 'totalBase' => 0, 'totalIva' => 0, 'totalVentas' => 0, 'totalCobrado' => 0],
            'cliente' => null, 'presupuesto' => ['clientePe' => null, 'timestampModPe' => null, 'manoobraPe' => 'Trabajo de prueba', 'importetotPe' => 0],
            'cobro' => ['fecha' => new \DateTimeImmutable('2026-01-01'), 'importeBruto' => 100, 'metodo' => 'efectivo', 'referencia' => 'PRUEBA', 'notas' => null],
            'proyecto' => ['nombre' => 'Proyecto de prueba', 'totalFacturado' => 100, 'pendienteCobro' => 0], 'pendiente' => 0,
            'project' => $project, 'cestaId' => 1, 'tipopago' => 'efectivo', 'espresu' => 'SI', 'importesnal' => 0, 'importe' => 0,
            'nombre' => 'Cliente de prueba', 'email' => 'prueba@example.test', 'tipo' => 'ducha',
            'total' => 100, 'materiales' => ['Material de prueba' => 50], 'mano_obra' => ['Trabajo de prueba' => 50],
        ]);
        $options = new Options();
        // The IA template's remote WhatsApp QR is deliberately not fetched in tests.
        $options->setIsRemoteEnabled(false);
        $options->setFontCache(sys_get_temp_dir());
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html);
        $pdf->render();
        $bytes = $pdf->output();
        self::assertStringStartsWith('%PDF-', $bytes);
        if ($template === 'iapresupuesto/presupuesto.html.twig') {
            self::assertStringContainsString('/Subtype /Image', $bytes);
        }
    }

    public function testPlansWithDimensionsAndDistancesRender(): void
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/templates'));
        $generator = new \App\Planos2D\Service\PlanoPdfGenerator($twig, new \App\Planos2D\Service\SvgPlanoSanitizer());
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect x="10" y="10" width="80" height="80" fill="none" stroke="black"/></svg>';
        $pdf = $generator->generar($svg, new \App\Planos2D\DTO\PlanoPdfData(), 'portrait', $svg, $svg);
        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function templates(): iterable
    {
        foreach (['factura', 'ticket'] as $type) {
            foreach (['pdf', 'pdf_trimestre', 'resumen_pdf'] as $variant) {
                yield ["admin_pro/$type/$variant.html.twig"];
            }
        }
        foreach (['factura_proveedor/exportar_listado', 'proyecto/justificante_cobro_pdf', 'iapresupuesto/presupuesto', 'presupuestobase', 'imprimir'] as $template) {
            yield [$template . '.html.twig'];
        }
    }
}
