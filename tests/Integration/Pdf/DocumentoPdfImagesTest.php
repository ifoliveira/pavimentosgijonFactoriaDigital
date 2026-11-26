<?php

namespace App\Tests\Integration\Pdf;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Service\Pdf\LocalImageDataUri;
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class DocumentoPdfImagesTest extends TestCase
{
    /** @dataProvider documents */
    public function testRealTemplatesGeneratePdfWithEmbeddedOrMissingLogo(string $template, string $type, bool $withLogo): void
    {
        $root = dirname(__DIR__, 3);
        $document = (new Documento())->setTipoDocumento($type)->setSerie('PRUEBA')->setNumero(1);
        $document->addLinea((new DocumentoLinea())
            ->setTipoLinea('producto')->setDescripcion('Pavimento de prueba')
            ->setCantidad('2.000')->setPrecioUnitario('50.00')->setSubtotal('100.00'));
        $logo = (new LocalImageDataUri($root, new NullLogger()))->convert($withLogo ? 'public/img/logo.png' : 'missing.png');
        $twig = new Environment(new FilesystemLoader($root . '/templates'));
        $html = $twig->render('documento/pdf-' . $template . '.html.twig', [
            'documento' => $document, 'iva' => '21 %', 'logoPath' => $logo,
        ]);
        self::assertStringNotContainsString('file://', $html);
        self::assertStringNotContainsString('/public/img/', $html);
        if ($withLogo) {
            self::assertStringContainsString('src="data:image/png;base64,', $html);
        } else {
            self::assertStringNotContainsString('<img', $html);
        }
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setChroot($root . '/public');
        $options->setFontCache(sys_get_temp_dir());
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html);
        $pdf->render();
        $bytes = $pdf->output();
        self::assertStringStartsWith('%PDF-', $bytes);
        if ($withLogo) {
            self::assertStringContainsString('/Subtype /Image', $bytes, 'The image must be embedded, not a broken image placeholder');
            self::assertStringContainsString('/SMask', $bytes, 'The original PNG alpha channel must be preserved');
        }
    }

    public function documents(): iterable
    {
        foreach (['clasico', 'moderno', 'cliente', 'cliente2'] as $template) {
            foreach (['presupuesto', 'factura'] as $type) {
                foreach ([true, false] as $withLogo) {
                    yield "$template-$type-" . (int) $withLogo => [$template, $type, $withLogo];
                }
            }
        }
    }
}
