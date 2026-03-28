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

    public function procesarFacturaPdf(string $rutaPdf): array
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