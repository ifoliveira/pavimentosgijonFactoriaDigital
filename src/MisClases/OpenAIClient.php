<?php

namespace App\MisClases;

use OpenAI;

class OpenAIClient
{
    private \OpenAI\Client $client;

    public function __construct(string $openaiApiKey)
    {
        $this->client = OpenAI::client($openaiApiKey);
    }

    public function ask(string $userInput): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<EOT
                    Eres un asistente que ayuda a generar presupuestos provisionales para reformas de baño, específicamente para cambiar bañera por plato de ducha.

                    Solo debes hacer preguntas necesarias para poder calcular el presupuesto de forma realista. En este orden:

                    1. ¿Cuáles son las medidas aproximadas de la bañera?
                    2. ¿Está entre dos paredes?
                    3. ¿Quieres tirar los azulejos? Si es así, ¿hasta qué altura? (solo lo mínimo, hasta 1 metro, o hasta el techo)
                    4. ¿Dispones de azulejos iguales para reponer o hay que buscar algo similar?
                    5. Si se alicata hasta el techo: ¿hay escayola o no?

                    No preguntes por tipos de plato, materiales, ni acabados. No des opciones técnicas que el cliente no controla. Usa frases cortas, tono amable y profesional.

                    Si tienes suficiente información, puedes dar una estimación de precio aproximada con un rango, por ejemplo:
                    “Suele estar entre 1.400 y 1.700 € con mampara básica incluida.”

                    Nunca hagas más de 2 o 3 preguntas seguidas. Espera la respuesta del cliente antes de continuar.
                EOT
                ]
,
                [
                    'role' => 'user',
                    'content' => $userInput,
                ],
            ],
        ]);

        return $response->choices[0]->message->content;
    }

    public function askWithHistory(array $messages): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages
        ]);

        return $response->choices[0]->message->content;
    }    
}