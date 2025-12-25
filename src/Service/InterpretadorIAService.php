<?php

namespace App\Service;

use App\MisClases\OpenAIClient;

use Psr\Log\LoggerInterface;



class InterpretadorIAService
{
    private OpenAIClient $openai;
    public function __construct(private LoggerInterface $logger, OpenAIClient $openai) 
    {
        $this->openai = $openai;
    }

    /**
     * Interpreta mediante OpenAI la respuesta del usuario
     * según la clave definida en el YAML del motor de pasos.
     *
     * EJEMPLO:
     *   clave = "tipo_mampara"
     *   respuestaUsuario = "la de corredera, creo"
     *
     * IA produce:
     *   { "tipo_mampara": "fijo_mas_corredera" }
     */


    public function interpretar(string $clave, string $respuestaUsuario, ?string $promptExtra = null): array
    {

        // Llamamos a tu nuevo método del OpenAIClient
        $interpretacion = $this->openai->interpretarDato($clave, $respuestaUsuario, $promptExtra);

        if (!is_array($interpretacion)) {
            // Fallback seguro si falla el JSON de OpenAI
            return [$clave => $respuestaUsuario];
        }

        return $interpretacion;
    }

    /**
     * Mezcla la interpretación nueva con el JSON acumulado
     */
    public function merge(array $jsonActual, array $nuevaInterpretacion): array
    {
        return array_merge($jsonActual, $nuevaInterpretacion);
    }

    /**
     * Determina si ya se han completado todas las preguntas
     * del YAML según el motor de pasos.
     *
     * pasoActual → índice que toca ahora
     * totalPasos → longitud del array de pasos
     *
     * EJEMPLO:
     *   totalPasos = 7
     *   último índice válido = 6
     *   pasoActual = 7 → COMPLETO
     */
    public function estaCompleto(int $pasoActual, int $totalPasos): bool
    {
        return $pasoActual >= $totalPasos;
    }
}

?>