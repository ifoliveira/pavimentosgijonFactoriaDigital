<?php

namespace App\Service\WebPresupuesto;

use App\Service\OpenAIClient;

final class OpenAiWebPresupuestoClient implements WebPresupuestoAiClientInterface
{
    public function __construct(
        private readonly OpenAIClient $openAIClient,
    ) {
    }

    public function generarPregunta(array $campos, array $valoresConocidos): string
    {
        $payload = [
            'tarea' => 'Genera una pregunta breve para cliente final.',
            'tono' => 'sencillo, cercano, profesional y poco tecnico',
            'reglas' => [
                'Pregunta solo por los campos recibidos.',
                'Puedes agrupar campos relacionados.',
                'No inventes datos, opciones ni defaults.',
                'No hables de JSON ni de campos tecnicos.',
            ],
            'campos_a_obtener' => $campos,
            'valores_conocidos' => $valoresConocidos,
        ];

        return trim($this->openAIClient->askWithHistory([
            [
                'role' => 'system',
                'content' => 'Eres un asesor de reformas. Devuelve solo la pregunta para el cliente.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ]));
    }

    public function interpretarRespuesta(array $campos, string $respuesta, array $valoresConocidos): array
    {
        $payload = [
            'tarea' => 'Extrae valores estructurados de la respuesta del cliente.',
            'formato_obligatorio' => [
                'valores_detectados' => new \stdClass(),
                'situacion' => 'RESUELTO|AMBIGUA|NO_ENTIENDE|NO_SABE_ELEGIR',
                'requiere_aclaracion' => false,
                'aclaracion' => '',
            ],
            'reglas' => [
                'Devuelve solo JSON valido.',
                'No inventes valores.',
                'No devuelvas campos que no esten en campos_a_obtener.',
                'Respeta tipos, unidades, opciones y restricciones.',
                'Si detectas al menos un valor valido, situacion debe ser RESUELTO.',
                'Si la respuesta es ambigua, valores_detectados debe quedar vacio, situacion debe ser AMBIGUA y requiere_aclaracion debe ser true.',
                'Si el cliente dice que no entiende, pide ejemplos o pregunta que significa una opcion, situacion debe ser NO_ENTIENDE.',
                'Si el cliente entiende la pregunta pero no sabe que opcion elegir o pide recomendacion, situacion debe ser NO_SABE_ELEGIR.',
                'No inventes explicaciones ni diferencias entre opciones: usa solo etiquetas, ayudas y descripciones recibidas.',
            ],
            'campos_a_obtener' => $campos,
            'valores_conocidos' => $valoresConocidos,
            'respuesta_cliente' => $respuesta,
        ];

        $raw = $this->openAIClient->askWithHistory([
            [
                'role' => 'system',
                'content' => 'Eres un normalizador estricto. Devuelve solo JSON valido.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ]);

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [
            'valores_detectados' => [],
            'situacion' => 'AMBIGUA',
            'requiere_aclaracion' => true,
            'aclaracion' => 'No he podido interpretar la respuesta con seguridad.',
        ];
    }
}
