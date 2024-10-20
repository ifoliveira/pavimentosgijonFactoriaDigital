<?php

namespace App\MisClases;

use GuzzleHttp\Client;

class OpenAiService
{
    private $client;
    private $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = $_ENV['OPENAI_API_KEY'];
    }

    public function generateProposalFromDescription(string $description): ?string
    {
        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-3.5-turbo',  // Asegúrate de que el modelo esté disponible
                    'messages'    => [
                        [
                            'role'    => 'user',
                            'content' => 'Prueba de mensaje para GPT-3.5-Turbo'
                        ]
                    ],
                    'max_tokens'  => 50,  // Limita los tokens para probar
                    'temperature' => 0.7,
                ]
            ]);
    
            // Verifica si la respuesta fue exitosa
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                return $data['choices'][0]['message']['content'] ?? 'No content returned';
            }
    
        } catch (\Exception $e) {
            // Imprime o guarda el mensaje de error completo para depurar
            return 'Error: ' . $e->getMessage();
        }

    
        if ($response->getStatusCode() !== 200) {
            return null;
        }
    
        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['text'] ?? null;
    }
}
