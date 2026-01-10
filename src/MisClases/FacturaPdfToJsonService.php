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
        $extension = pathinfo($rutaPdf, PATHINFO_EXTENSION);
        if (strtolower($extension) === 'pdf') {
            // Extraer texto del PDF
            $parser = new Parser();
            $pdf = $parser->parseFile($rutaPdf);
            $textoFactura = $pdf->getText();
            // Limpieza básica
            $textoFactura = preg_replace('/[ \t]+/', ' ', $textoFactura); // Reemplaza tabulaciones y espacios múltiples por uno solo
            $textoFactura = preg_replace('/[\r\n]+/', "\n", $textoFactura); // Unifica saltos de línea
            $textoFactura = trim($textoFactura);

            // Prompt de entrada
            $prompt = $this->getPromptConJsonEstandar($textoFactura);
            

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
            // Elimina caracteres de control no válidos (como tabs)
            $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);
            
            $json = json_decode($content, true);
            
            if ($json === null) {
                throw new \RuntimeException('Error al parsear JSON: ' . json_last_error_msg() . "\nContenido:\n" . $content);
            }
            
            return $json;
        } elseif (strtolower($extension) === 'jpg' || strtolower($extension) === 'jpeg' || strtolower($extension) === 'png') {
          $base64 = base64_encode(file_get_contents($rutaPdf)); // imagen es ruta real
      
          $prompt = $this->getPromptConJsonEstandar('[Factura visual en imagen]');
      
          $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
              'headers' => [
                  'Authorization' => 'Bearer ' . $this->openaiApiKey,
                  'Content-Type' => 'application/json',
              ],
              'json' => [
                  'model' => 'gpt-4-turbo',
                  'messages' => [
                      [
                          "role" => "user",
                          "content" => [
                              ["type" => "text", "text" => $prompt],
                              ["type" => "image_url", "image_url" => [
                                  "url" => "data:image/jpeg;base64," . $base64
                              ]]
                          ]
                      ]
                  ],
                  'max_tokens' => 2000
              ]
          ]);
      
          $data = $response->toArray();
          $content = trim($data['choices'][0]['message']['content'] ?? '{}');
      
          $content = preg_replace('/\/\/.*$/m', '', $content);
          $content = preg_replace('/,\s*}/', '}', $content);
          $content = preg_replace('/,\s*]/', ']', $content);
          // Elimina caracteres de control no válidos (como tabs)
          $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);          
      
          $json = json_decode($content, true);
          if ($json === null) {
              throw new \RuntimeException('Error al parsear JSON: ' . json_last_error_msg() . "\nContenido:\n" . $content);
          }
      
          return $json;
      }
    }

    private function getPromptConJsonEstandar(string $contenidoFactura): string
    {
        return <<<EOT

    1. Extrae primero la tabla de artículos de la factura.
    2. Para cada fila de la tabla identifica las columnas: 
      - código
      - descripción
      - cantidad
      - precio unitario
      - descuento
      - importe final
    3. Si ves columnas adicionales o combinadas, intenta mapearlas según estas reglas:
      - Si hay más de 5 columnas, asume que la 1ª es código, la última es importe final.
      - Si falta cantidad explícita, intenta inferirla por división: cantidad = total / precio_unitario, si no hay descuento.
    4. Todos los valores numéricos deben estar en formato float con punto decimal.
    5. No generes texto fuera del JSON.
    6. Si hay campos no detectados, pon null.
            
    Devuélveme exclusivamente el siguiente JSON COMPLETO (sin explicaciones ni texto adicional). 
    Si no puedes estar 100% seguro de qué valor corresponde, define el campo como null.
    No adivines valores implícitos sin aplicar una regla de cálculo clara.
    No añadas ni quites campos.
    
    Estructura esperada:
    {
      "numero_factura": null,
      "fecha_factura": null,
      "cliente": {
        "nombre": null,
        "direccion": null,
        "cif": null,
        "telefono": []
      },
      "articulos": [
        {
          "codigo": null,
          "descripcion": null,
          "cantidad": null,
          "precio_unitario": null,
          "descuento_porcentaje": null,
          "importe_final": null
        }
      ],
      "importe_bruto": null,
      "base_imponible": null,
      "iva": {
        "porcentaje": null,
        "importe": null
      },
      "recargo_equivalencia": {
        "porcentaje": null,
        "importe": null
      },
      "total_factura": null,
      "forma_pago": null,
      "vencimientos": [
        {
          "fecha": null,
          "importe": null
        }
      ],
      "empresa_emisora": {
        "nombre": null,
        "cif": null,
        "direccion": null
      },
      "cuenta_bancaria": null
    }
    
    ⚠️ INSTRUCCIONES:
    - Extrae todos los artículos sin omitir ninguno, aunque estén repetidos o el formato sea irregular.
    - Asegúrate de completar cada campo del JSON con los valores exactos de la factura. 
    - Si algún campo no aparece, déjalo como null o vacío según corresponda.
    - No generes explicaciones ni introducción, solo la estructura JSON con los datos completos.
    - Usa punto como separador decimal (ej: 9.72), no coma.
    - No incluyas el símbolo %, solo el número como float.
    - Si falta precio_unitario pero hay importe_final y cantidad, calcula:
        precio_unitario = importe_final / cantidad
    - Si falta cantidad pero hay precio_unitario e importe_final:
        cantidad = importe_final / precio_unitario
    - Si solo hay dos números en la fila, interpreta primero como cantidad, segundo como importe.
    - Si en la línea de un artículo aparece un valor llamado "dto", "dcto", "descuento" o similar, asúmelo como un porcentaje de descuento sobre el precio unitario.
    - Si no aparece descuento, asume que el descuento_porcentaje es 0.00    
    Ejemplo:
    "3 uds. x 30,00 dto 40%" → cantidad: 3, precio_unitario: 30.00, descuento_porcentaje: 40.00, importe_final: 54.00

    
    Contenido de la factura:
    
    $contenidoFactura
    EOT;
    }
    
}
