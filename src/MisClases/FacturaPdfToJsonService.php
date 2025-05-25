<?php

namespace App\MisClases;

use Smalot\PdfParser\Parser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacturaPdfToJsonService
{
    private HttpClientInterface $client;
    private string $openaiApiKey;

    public function __construct(HttpClientInterface $client, string $openaiApiKey)
    {
        $this->client = $client;
        $this->openaiApiKey = $openaiApiKey;
    }

    public function procesarFacturaPdf(string $rutaPdf): array
    {
        // Extraer texto del PDF
        $parser = new Parser();
        $pdf = $parser->parseFile($rutaPdf);
        $textoFactura = $pdf->getText();

        // Prompt de entrada
        $prompt = "
        Devuélveme exclusivamente el siguiente JSON COMPLETO (sin explicaciones ni texto adicional). No añadas ni quites campos.
        
        Estructura esperada:
        {
          \"numero_factura\": null,
          \"fecha_factura\": null,
          \"cliente\": {
            \"nombre\": null,
            \"direccion\": null,
            \"cif\": null,
            \"telefono\": []
          },
          \"articulos\": [
            {
              \"codigo\": null,
              \"descripcion\": null,
              \"cantidad\": null,
              \"precio_unitario\": null,
              \"descuento_porcentaje\": null,
              \"importe_final\": null
            }
          ],
          \"importe_bruto\": null,
          \"base_imponible\": null,
          \"iva\": {
            \"porcentaje\": null,
            \"importe\": null
          },
          \"recargo_equivalencia\": {
            \"porcentaje\": null,
            \"importe\": null
          },
          \"total_factura\": null,
          \"forma_pago\": null,
          \"vencimientos\": [
            {
              \"fecha\": null,
              \"importe\": null
            }
          ],
          \"empresa_emisora\": {
            \"nombre\": null,
            \"cif\": null,
            \"direccion\": null
          },
          \"cuenta_bancaria\": null
        }
        
        ⚠️ INSTRUCCIONES:
        - Extrae todos los artículos sin omitir ninguno, aunque estén repetidos o el formato sea irregular.
        - Asegúrate de completar cada campo del JSON con los valores exactos de la factura. 
        - Si algún campo no aparece, déjalo como null o vacío según corresponda.
        - No generes explicaciones ni introducción, solo la estructura JSON con los datos completos.
        
        Contenido del texto extraído del PDF:
        
        $textoFactura
        ";
        

        // Llamada a la API de OpenAI
        $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ["role" => "user", "content" => $prompt]
                ],
                'temperature' => 0.2
            ]
        ]);

        $data = $response->toArray();
        $content = trim($data['choices'][0]['message']['content'] ?? '{}');

        // Asegurar que no contiene comentarios o basura
        $content = preg_replace('/\/\/.*$/m', '', $content); // Elimina comentarios JS
        $content = preg_replace('/,\s*}/', '}', $content);   // Quita comas finales inválidas
        $content = preg_replace('/,\s*]/', ']', $content);
        
        $json = json_decode($content, true);
        
        if ($json === null) {
            throw new \RuntimeException('Error al parsear JSON: ' . json_last_error_msg() . "\nContenido:\n" . $content);
        }
        
        return $json;
    }
}
