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
            $texto  = $parser->parseFile($rutaPdf)->getText();
            $texto  = preg_replace('/[ \t]+/', ' ', $texto);
            $texto  = preg_replace('/[\r\n]+/', "\n", $texto);
            $texto  = trim($texto);

            $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-3.5-turbo',
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
    1. Extrae primero la tabla de artículos de la factura.
    2. Para cada fila de la tabla identifica las columnas: 
      - código, descripción, cantidad, precio unitario, descuento, importe final
    3. Todos los valores numéricos deben estar en formato float con punto decimal.
    4. No generes texto fuera del JSON.
    5. Si hay campos no detectados, pon null.

    Devuélveme exclusivamente el siguiente JSON COMPLETO (sin explicaciones ni texto adicional).

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

    Contenido de la factura:
    $contenidoFactura
    EOT;
    }
}