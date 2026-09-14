<?php

namespace App\Planos2D\Service;

use App\Planos2D\DTO\PlanoPdfData;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class PlanoPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SvgPlanoSanitizer $svgPlanoSanitizer,
    ) {
    }

    public function generar(string $svg, PlanoPdfData $datos, ?string $orientacion = null, ?string $svgDistancias = null, ?string $svgDimensiones = null): string
    {
        $svgLimpio = $this->svgPlanoSanitizer->limpiar($svg);
        $svgDimensionesLimpio = is_string($svgDimensiones) && trim($svgDimensiones) !== ''
            ? $this->svgPlanoSanitizer->limpiar($svgDimensiones)
            : null;
        $svgDistanciasLimpio = is_string($svgDistancias) && trim($svgDistancias) !== ''
            ? $this->svgPlanoSanitizer->limpiar($svgDistancias)
            : null;
        $orientacionFinal = $this->resolverOrientacion($orientacion, $svgLimpio);
        $layoutPlano = $this->calcularLayoutPlano($svgLimpio, $orientacionFinal);
        $layoutPlanoDimensiones = $svgDimensionesLimpio
            ? $this->calcularLayoutPlano($svgDimensionesLimpio, $orientacionFinal)
            : null;
        $layoutPlanoDistancias = $svgDistanciasLimpio
            ? $this->calcularLayoutPlano($svgDistanciasLimpio, $orientacionFinal)
            : null;
        $html = $this->twig->render('planos2d/pdf/plano.html.twig', [
            'svg' => $svgLimpio,
            'svgDataUri' => 'data:image/svg+xml;base64,' . base64_encode($svgLimpio),
            'svgDimensionesDataUri' => $svgDimensionesLimpio ? 'data:image/svg+xml;base64,' . base64_encode($svgDimensionesLimpio) : null,
            'svgDistanciasDataUri' => $svgDistanciasLimpio ? 'data:image/svg+xml;base64,' . base64_encode($svgDistanciasLimpio) : null,
            'datos' => $datos,
            'orientacion' => $orientacionFinal,
            'layoutPlano' => $layoutPlano,
            'layoutPlanoDimensiones' => $layoutPlanoDimensiones,
            'layoutPlanoDistancias' => $layoutPlanoDistancias,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientacionFinal);
        $dompdf->render();

        return $dompdf->output();
    }

    private function resolverOrientacion(?string $orientacion, string $svg): string
    {
        if (in_array($orientacion, ['portrait', 'landscape'], true)) {
            return $orientacion;
        }

        $viewBox = $this->svgPlanoSanitizer->leerViewBoxDesdeSvg($svg);

        if (count($viewBox) !== 4) {
            return 'landscape';
        }

        [, , $ancho, $alto] = $viewBox;

        return $alto > $ancho * 1.12 ? 'portrait' : 'landscape';
    }

    private function calcularLayoutPlano(string $svg, string $orientacion): array
    {
        $viewBox = $this->svgPlanoSanitizer->leerViewBoxDesdeSvg($svg);

        if (count($viewBox) !== 4) {
            return [
                'anchoMm' => $orientacion === 'portrait' ? 182 : 269,
                'altoMm' => $orientacion === 'portrait' ? 182 : 128,
            ];
        }

        [, , $anchoViewBox, $altoViewBox] = $viewBox;
        $anchoDisponible = $orientacion === 'portrait' ? 182 : 269;
        $altoDisponible = $orientacion === 'portrait' ? 218 : 128;
        $escala = min($anchoDisponible / $anchoViewBox, $altoDisponible / $altoViewBox);

        return [
            'anchoMm' => round($anchoViewBox * $escala, 2),
            'altoMm' => round($altoViewBox * $escala, 2),
        ];
    }
}
