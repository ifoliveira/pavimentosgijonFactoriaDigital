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

    // ───────────────────────────────
    //  MÉTODOS QUE YA EXISTEN
    // ───────────────────────────────

    public function ask(string $userInput): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' =>
                    "Eres un asistente experto en reformas."
                ],
                ['role' => 'user', 'content' => $userInput],
            ],
        ]);

        return $response->choices[0]->message->content;
    }

    public function askWithHistory(array $messages): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages
        ]);

        return $response->choices[0]->message->content;
    }

    // ───────────────────────────────
    //  A) INTERPRETAR RESPUESTA DEL USUARIO (Motor de Paso)
    // ───────────────────────────────

    /**
     * Normaliza una respuesta del usuario
     * y devuelve SOLO un JSON limpio.
     */
    /**
     * Interpreta un dato usando OpenAI, con soporte para prompt personalizado.
     */
    public function interpretarDato(string $clave, string $respuesta, ?string $promptExtra = null): ?array
    {
        // Prompt base del sistema (siempre presente)
        $system = "
    Eres un sistema de normalización de datos para presupuestos de reformas.
    Debes devolver únicamente un JSON válido sin texto adicional.
        ";

        // Si el YAML trae un prompt específico para este paso, úsalo.
        // Si no, usa el prompt genérico actual.
        $userPrompt = $promptExtra
            ? $promptExtra
            : "
    Devuelve solo un JSON válido.
    La clave debe ser '$clave' y el valor debe ser la normalización de la respuesta del usuario.
            ";

        // Añadimos la respuesta del usuario al final del prompt.
        $userPrompt .= "\n\nRespuesta del usuario: \"$respuesta\"\n";

        // Llamada al modelo
        $raw = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0
        ]);

        // Obtenemos el mensaje devuelto
        $json = $raw->choices[0]->message->content ?? null;

        // Convertir a array y fallback
        return json_decode($json, true) ?: null;
    }


    // ───────────────────────────────
    //  C) TEXTO COMERCIAL FINAL
    // ───────────────────────────────

    public function textoComercial(array $json, float $precioFinal): string
    {
        $payload = "
Genera un texto comercial profesional para un presupuesto de reforma de baño.

JSON de datos:
" . json_encode($json, JSON_PRETTY_PRINT) . "

Precio final estimado: {$precioFinal} €

Características del texto:
- claro, cercano y experto
- explica qué incluye la obra
- refuerza confianza
- usa urgencia suave (“tenemos hueco esta semana”)
- 4–6 párrafos
        ";

        $resp = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un redactor comercial experto en reformas.'],
                ['role' => 'user',   'content' => $payload],
            ],
            'temperature' => 0.7
        ]);

        return $resp->choices[0]->message->content;
    }
}
