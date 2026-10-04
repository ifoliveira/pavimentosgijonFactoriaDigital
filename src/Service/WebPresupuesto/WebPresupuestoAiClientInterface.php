<?php

namespace App\Service\WebPresupuesto;

interface WebPresupuestoAiClientInterface
{
    /**
     * @param array<int, array<string, mixed>> $campos
     * @param array<string, mixed> $valoresConocidos
     */
    public function generarPregunta(array $campos, array $valoresConocidos): string;

    /**
     * @param array<string, mixed> $contexto
     *
     * @return array{pregunta?: string}
     */
    public function generarPreguntaConversacional(array $contexto): array;

    /**
     * @param array<int, array<string, mixed>> $campos
     * @param array<string, mixed> $valoresConocidos
     *
     * @return array{valores_detectados?: array<string, mixed>, situacion?: string, requiere_aclaracion?: bool, aclaracion?: string}
     */
    public function interpretarRespuesta(array $campos, string $respuesta, array $valoresConocidos): array;

    /**
     * @param array<string, mixed> $contexto
     *
     * @return array{valores?: array<string, mixed>, situacion?: string, requiere_aclaracion?: bool, aclaracion?: string}
     */
    public function interpretarRespuestaConversacional(array $contexto, string $respuesta): array;
}
