<?php
namespace App\Service;

use Smalot\PdfParser\Parser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacturaPdfToJsonService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $openaiApiKey,
    ) {}
    
public function procesarFacturaPdf(string $rutaArchivo): array
{
    if (!is_file($rutaArchivo) || !is_readable($rutaArchivo)) {
        throw new \InvalidArgumentException(
            sprintf('El archivo no existe o no es legible: %s', $rutaArchivo)
        );
    }

    $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

    $payload = match ($extension) {
        'pdf' => $this->crearPayloadPdf($rutaArchivo),
        'jpg', 'jpeg', 'png', 'webp' => $this->crearPayloadImagen($rutaArchivo),
        default => throw new \InvalidArgumentException(
            "Extensión no soportada: {$extension}"
        ),
    };

    $response = $this->client->request(
        'POST',
        'https://api.openai.com/v1/responses',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 90,
        ]
    );

    $respuesta = $response->toArray(false);

    $content = $this->extraerTextoRespuesta($respuesta);

    if ($content === '') {
        throw new \RuntimeException(
            'OpenAI no devolvió contenido procesable: ' .
            json_encode($respuesta, JSON_UNESCAPED_UNICODE)
        );
    }

    $json = json_decode($this->limpiarJson($content), true);

    if (!is_array($json)) {
        throw new \RuntimeException(
            'Error al parsear JSON: ' .
            json_last_error_msg() .
            "\nContenido recibido:\n" .
            $content
        );
    }

    return $this->validarResultadoFactura($json);
}

private function crearPayloadPdf(string $rutaPdf): array
{
    $texto = $this->extraerTextoPdf($rutaPdf);

    /*
     * No basta con comprobar strlen().
     * Algunos PDF escaneados devuelven basura o cuatro números sueltos.
     */
    if ($this->textoPdfEsUtil($texto)) {
        return $this->crearPayloadTexto($texto);
    }

    /*
     * Si el texto no es aprovechable, enviamos directamente el PDF.
     * El modelo verá las páginas como imágenes.
     */
    $base64 = base64_encode(
        $this->leerArchivo($rutaPdf)
    );

    return [
        'model' => 'gpt-5.6',
        'input' => [[
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_file',
                    'filename' => basename($rutaPdf),
                    'file_data' => 'data:application/pdf;base64,' . $base64,
                    'detail' => 'high',
                ],
                [
                    'type' => 'input_text',
                    'text' => $this->getPromptConJsonEstandar(
                        '[Factura proporcionada como PDF visual. Lee directamente las páginas del documento.]'
                    ),
                ],
            ],
        ]],
    'reasoning' => [
        'effort' => 'low',
    ],
    'text' => [
        'verbosity' => 'low',
    ],
    'max_output_tokens' => 4000,
    ];
}

private function extraerTextoPdf(string $rutaPdf): string
{
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($rutaPdf);

        $paginas = [];

        foreach ($pdf->getPages() as $numero => $page) {
            $textoPagina = trim($page->getText());

            if ($textoPagina !== '') {
                $paginas[] = sprintf(
                    "--- PÁGINA %d ---\n%s",
                    $numero + 1,
                    $textoPagina
                );
            }
        }

        $texto = implode("\n\n", $paginas);
        $texto = preg_replace('/[\r\n]{3,}/', "\n\n", $texto);

        return trim((string) $texto);
    } catch (\Throwable $e) {
        /*
         * Un fallo del parser no debe impedir procesar el PDF visualmente.
         */
        return '';
    }
}

private function textoPdfEsUtil(string $texto): bool
{
    $texto = trim($texto);

    if (mb_strlen($texto) < 150) {
        return false;
    }

    $textoSinEspacios = preg_replace('/\s+/u', '', $texto);

    if ($textoSinEspacios === '') {
        return false;
    }

    /*
     * Porcentaje aproximado de caracteres reconocibles.
     */
    preg_match_all(
        '/[\p{L}\p{N}€$%.,:;\/\-]/u',
        $textoSinEspacios,
        $coincidencias
    );

    $totalCaracteres = mb_strlen($textoSinEspacios);
    $caracteresValidos = count($coincidencias[0]);

    if ($totalCaracteres === 0) {
        return false;
    }

    $porcentajeValido = $caracteresValidos / $totalCaracteres;

    if ($porcentajeValido < 0.65) {
        return false;
    }

    /*
     * Señales habituales de una factura.
     * No exigimos todas, porque cada proveedor utiliza un diseño distinto.
     */
    $patronesFactura = [
        '/factura/ui',
        '/fecha/ui',
        '/total/ui',
        '/importe/ui',
        '/base\s*imponible/ui',
        '/i\.?v\.?a\.?/ui',
        '/vencimiento/ui',
    ];

    $coincidenciasFactura = 0;

    foreach ($patronesFactura as $patron) {
        if (preg_match($patron, $texto)) {
            $coincidenciasFactura++;
        }
    }

    return $coincidenciasFactura >= 2;
}

private function crearPayloadTexto(string $texto): array
{
    return [
        'model' => 'gpt-5.6',
        'input' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => $this->getPromptConJsonEstandar($texto),
            ]],
        ]],
    'reasoning' => [
        'effort' => 'low',
    ],
    'text' => [
        'verbosity' => 'low',
    ],
    'max_output_tokens' => 4000,
    ];
}

private function crearPayloadImagen(string $rutaImagen): array
{
    $contenido = $this->leerArchivo($rutaImagen);
    $base64 = base64_encode($contenido);

    $mimeType = mime_content_type($rutaImagen);

    if (!in_array($mimeType, [
        'image/jpeg',
        'image/png',
        'image/webp',
    ], true)) {
        throw new \InvalidArgumentException(
            "Tipo de imagen no soportado: {$mimeType}"
        );
    }

    return [
        'model' => 'gpt-5.6',
        'input' => [[
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => $this->getPromptConJsonEstandar(
                        '[Factura visual proporcionada como imagen]'
                    ),
                ],
                [
                    'type' => 'input_image',
                    'image_url' => sprintf(
                        'data:%s;base64,%s',
                        $mimeType,
                        $base64
                    ),
                    'detail' => 'high',
                ],
            ],
        ]],
    'reasoning' => [
        'effort' => 'low',
    ],
    'text' => [
        'verbosity' => 'low',
    ],
    'max_output_tokens' => 4000,
    ];
}

private function leerArchivo(string $ruta): string
{
    $contenido = file_get_contents($ruta);

    if ($contenido === false) {
        throw new \RuntimeException(
            sprintf('No se pudo leer el archivo: %s', $ruta)
        );
    }

    return $contenido;
}

private function extraerTextoRespuesta(array $respuesta): string
{
    /*
     * Algunas respuestas pueden incluir output_text directamente.
     */
    if (
        isset($respuesta['output_text']) &&
        is_string($respuesta['output_text'])
    ) {
        return trim($respuesta['output_text']);
    }

    foreach ($respuesta['output'] ?? [] as $output) {
        foreach ($output['content'] ?? [] as $contenido) {
            if (
                ($contenido['type'] ?? null) === 'output_text' &&
                isset($contenido['text'])
            ) {
                return trim((string) $contenido['text']);
            }
        }
    }

    return '';
}


private function validarResultadoFactura(array $datos): array
{
    $total = isset($datos['total_factura'])
        ? (float) $datos['total_factura']
        : null;

    $base = isset($datos['base_imponible'])
        ? (float) $datos['base_imponible']
        : null;

    $iva = isset($datos['iva'])
        ? (float) $datos['iva']
        : null;

    if ($total !== null && $base !== null && $iva !== null) {
        $diferencia = abs(($base + $iva) - $total);

        $datos['validaciones']['base_mas_iva_coincide'] =
            $diferencia <= 0.02;
    }

    $sumaVencimientos = 0.0;

    foreach ($datos['vencimientos'] ?? [] as $vencimiento) {
        $sumaVencimientos += (float) ($vencimiento['importe'] ?? 0);
    }

    if ($total !== null && !empty($datos['vencimientos'])) {
        $datos['validaciones']['vencimientos_coinciden_total'] =
            abs($sumaVencimientos - $total) <= 0.02;
    }

    return $datos;
}


    public function procesarFacturaPdfOld(string $rutaPdf): array
    {
        $extension = strtolower(pathinfo($rutaPdf, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $parser = new Parser();
            $pdf    = $parser->parseFile($rutaPdf);

            // Extrae página a página preservando saltos de línea reales
            $paginas = [];
            foreach ($pdf->getPages() as $page) {
                $paginas[] = $page->getText();
            }
            $texto = implode("\n---\n", $paginas);

            // Solo normaliza saltos múltiples, NO colapses espacios horizontales
            $texto = preg_replace('/[\r\n]{3,}/', "\n\n", $texto);
            $texto = trim($texto);

            $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages'    => [['role' => 'user', 'content' => $this->getPromptConJsonEstandar($texto)]],
                    'temperature' => 0.2,
                ],
            ]);

        } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $base64 = base64_encode(file_get_contents($rutaPdf));

            $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'      => 'gpt-4-turbo',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => [
                            ['type' => 'text',      'text'      => $this->getPromptConJsonEstandar('[Factura visual en imagen]')],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $base64]],
                        ],
                    ]],
                    'max_tokens' => 2000,
                ],
            ]);
        } else {
            throw new \InvalidArgumentException("Extensión no soportada: {$extension}");
        }

        $content = trim($response->toArray()['choices'][0]['message']['content'] ?? '{}');
        $json    = json_decode($this->limpiarJson($content), true);

        if ($json === null) {
            throw new \RuntimeException('Error al parsear JSON: ' . json_last_error_msg() . "\nContenido:\n" . $content);
        }

        return $json;
    }

    // Extraído para eliminar duplicación entre bloque PDF e imagen
    private function limpiarJson(string $content): string
    {
        $content = preg_replace('/\/\/.*$/m', '', $content);
        $content = preg_replace('/,\s*}/', '}', $content);
        $content = preg_replace('/,\s*]/', ']', $content);
        $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);

        return $content;
    }
    private function getPromptConJsonEstandar(string $contenidoFactura): string
    {
        return <<<EOT
        Eres un extractor de datos de facturas españolas. Analiza la factura proporcionada, que puede llegar como texto extraído, imagen o PDF visual.


    REGLAS IMPORTANTES:
    - El texto puede estar desordenado por columnas; reconstruye la tabla de artículos buscando patrones: código → descripción → cantidad → precio → importe.
    - Las líneas sin importe (como válvulas incluidas gratis) tienen importe null o 0.
    - Todos los números usan coma decimal en el original; conviértelos a float con punto (168,60 → 168.60).
    - Las fechas en formato DD/MM/AAAA conviértelas a AAAA-MM-DD.
    - Si un campo no aparece, pon null.
    - Devuelve EXCLUSIVAMENTE el JSON, sin explicaciones, sin bloques de código, sin texto extra.

    JSON a rellenar:
    {
    "numero_factura": null,
    "fecha_factura": null,
    "cliente": { "nombre": null, "direccion": null, "cif": null, "telefono": [] },
    "articulos": [{ "codigo": null, "descripcion": null, "cantidad": null, "precio_unitario": null, "descuento_porcentaje": null, "importe_final": null }],
    "importe_bruto": null,
    "base_imponible": null,
    "iva": { "porcentaje": null, "importe": null },
    "recargo_equivalencia": { "porcentaje": null, "importe": null },
    "total_factura": null,
    "forma_pago": null,
    "vencimientos": [{ "fecha": null, "importe": null }],
    "empresa_emisora": { "nombre": null, "cif": null, "direccion": null },
    "cuenta_bancaria": null
    }

    Texto de la factura:
    $contenidoFactura
    EOT;
    }

    private function getPromptConJsonEstandarOLD(string $contenidoFactura): string
    {
        return <<<EOT
    Eres un extractor de datos de facturas españolas. Analiza el siguiente texto extraído de un PDF.

    REGLAS IMPORTANTES:
    - El texto puede estar desordenado por columnas; reconstruye la tabla de artículos buscando patrones: código → descripción → cantidad → precio → importe.
    - Las líneas sin importe (como válvulas incluidas gratis) tienen importe null o 0.
    - Todos los números usan coma decimal en el original; conviértelos a float con punto (168,60 → 168.60).
    - Las fechas en formato DD/MM/AAAA conviértelas a AAAA-MM-DD.
    - Si un campo no aparece, pon null.
    - Devuelve EXCLUSIVAMENTE el JSON, sin explicaciones, sin bloques de código, sin texto extra.

    JSON a rellenar:
    {
    "numero_factura": null,
    "fecha_factura": null,
    "cliente": { "nombre": null, "direccion": null, "cif": null, "telefono": [] },
    "articulos": [{ "codigo": null, "descripcion": null, "cantidad": null, "precio_unitario": null, "descuento_porcentaje": null, "importe_final": null }],
    "importe_bruto": null,
    "base_imponible": null,
    "iva": { "porcentaje": null, "importe": null },
    "recargo_equivalencia": { "porcentaje": null, "importe": null },
    "total_factura": null,
    "forma_pago": null,
    "vencimientos": [{ "fecha": null, "importe": null }],
    "empresa_emisora": { "nombre": null, "cif": null, "direccion": null },
    "cuenta_bancaria": null
    }

    Texto de la factura:
    $contenidoFactura
    EOT;
    }
}