<?php

namespace App\Tests\Templates;

use App\Entity\Documento;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

final class DocumentoIndexViewTest extends KernelTestCase
{
    public function testListadoConPresupuestosAceptadosYEnviados(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        $twig->setLoader(new ChainLoader([
            new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']),
            $twig->getLoader(),
        ]));
        $documentos = [];
        foreach (['aceptado', 'enviado', 'entregado'] as $i => $estado) {
            $doc = (new Documento())->setTipoDocumento('presupuesto')->setEstadoComercial($estado)
                ->setSerie('P2026')->setNumero($i + 1);
            (new \ReflectionProperty(Documento::class, 'id'))->setValue($doc, $i + 1);
            $documentos[] = $doc;
        }
        $html = $twig->render('documento/index.html.twig', ['documentos' => $documentos]);
        foreach ($documentos as $doc) {
            self::assertStringContainsString($doc->getNumeroFormateado(), $html);
            self::assertStringContainsString(self::getContainer()->get(RouterInterface::class)
                ->generate('app_documento_show', ['id' => $doc->getId()]), $html);
        }
        self::assertStringNotContainsString('Convertir a factura', $html);
    }
}
