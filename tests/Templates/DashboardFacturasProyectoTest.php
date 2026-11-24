<?php

namespace App\Tests\Templates;

use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final class DashboardFacturasProyectoTest extends KernelTestCase
{
    public function testAccesoDirectoATodasLasFacturasDelProyecto(): void
    {
        self::bootKernel();
        $proyecto = new Proyecto();
        $anticipo = $this->documento(101, 'factura', 1);
        $anticipo->asignarCobroAnticipo(new ProyectoCobro());
        $final = $this->documento(102, 'factura', 2);
        $proyecto->addDocumento($anticipo);
        $proyecto->addDocumento($final);
        $proyecto->addDocumento($this->documento(103, 'presupuesto', 3));
        $html = self::getContainer()->get(Environment::class)->render('admin_pro/_facturas_proyecto.html.twig', ['proyecto' => $proyecto]);
        $crawler = new Crawler($html);
        $router = self::getContainer()->get(RouterInterface::class);

        self::assertStringContainsString('Anticipo', $html);
        self::assertStringContainsString('Final', $html);
        self::assertStringContainsString('F2026-0001', $html);
        self::assertStringContainsString('F2026-0002', $html);
        self::assertStringNotContainsString('F2026-0003', $html);
        self::assertCount(2, $crawler->filter('a[target="_blank"]'));
        foreach ([$anticipo, $final] as $factura) {
            $pdf = $router->generate('app_documento_pdf', ['id' => $factura->getId(), 'tpl' => 'clasico', 'ocultar_importes' => 1]);
            self::assertSame($pdf, $crawler->selectLink('Imprimir PDF')->filter('[href="'.$pdf.'"]')->attr('href'));
            self::assertStringContainsString($router->generate('app_documento_show', ['id' => $factura->getId()]), $html);
        }
    }

    public function testProyectoSinFacturas(): void
    {
        self::bootKernel();
        $html = self::getContainer()->get(Environment::class)->render('admin_pro/_facturas_proyecto.html.twig', ['proyecto' => new Proyecto()]);
        self::assertStringContainsString('Sin facturas generadas.', $html);
        self::assertStringNotContainsString('Imprimir PDF', $html);
    }

    private function documento(int $id, string $tipo, int $numero): Documento
    {
        $documento = (new Documento())->setTipoDocumento($tipo)->setSerie('F2026')->setNumero($numero)->setTotal('1000.00');
        (new \ReflectionProperty(Documento::class, 'id'))->setValue($documento, $id);
        return $documento;
    }
}
