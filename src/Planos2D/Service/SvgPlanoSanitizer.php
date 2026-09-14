<?php

namespace App\Planos2D\Service;

final class SvgPlanoSanitizer
{
    public function limpiar(string $svg): string
    {
        if (trim($svg) === '') {
            throw new \InvalidArgumentException('El SVG del plano es obligatorio.');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previo = libxml_use_internal_errors(true);
        $cargado = $dom->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if (!$cargado || $dom->documentElement?->tagName !== 'svg') {
            throw new \InvalidArgumentException('El SVG del plano no es valido.');
        }

        $this->eliminarTags($dom, ['script', 'foreignObject']);
        $this->limpiarAtributos($dom);

        $viewBox = $dom->documentElement->getAttribute('viewBox');
        if ($viewBox === '' || count($this->leerViewBox($viewBox)) !== 4) {
            throw new \InvalidArgumentException('El SVG del plano debe incluir viewBox.');
        }

        return $dom->saveXML($dom->documentElement);
    }

    public function leerViewBoxDesdeSvg(string $svg): array
    {
        if (!preg_match('/viewBox="([^"]+)"/', $svg, $coincidencias)) {
            return [];
        }

        return $this->leerViewBox($coincidencias[1]);
    }

    private function eliminarTags(\DOMDocument $dom, array $tags): void
    {
        foreach ($tags as $tag) {
            while (($nodos = $dom->getElementsByTagName($tag))->length > 0) {
                $nodo = $nodos->item(0);
                $nodo?->parentNode?->removeChild($nodo);
            }
        }
    }

    private function limpiarAtributos(\DOMDocument $dom): void
    {
        foreach ($dom->getElementsByTagName('*') as $nodo) {
            if (!$nodo instanceof \DOMElement) {
                continue;
            }

            $atributos = [];
            foreach ($nodo->attributes as $atributo) {
                $atributos[] = $atributo->name;
            }

            foreach ($atributos as $nombre) {
                if (str_starts_with($nombre, 'on') || str_starts_with($nombre, 'data-')) {
                    $nodo->removeAttribute($nombre);
                }
            }
        }
    }

    private function leerViewBox(string $viewBox): array
    {
        $valores = preg_split('/[\s,]+/', trim($viewBox));

        if ($valores === false || count($valores) !== 4) {
            return [];
        }

        $numeros = array_map('floatval', $valores);

        foreach ($numeros as $numero) {
            if (!is_finite($numero)) {
                return [];
            }
        }

        return $numeros;
    }
}
