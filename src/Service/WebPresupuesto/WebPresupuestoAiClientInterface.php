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
     * @param array<int, array<string, mixed>> $campos
     * @param array<string, mixed> $valoresConocidos
     *
     * @return array{valores_detectados?: array<string, mixed>, situacion?: string, requiere_aclaracion?: bool, aclaracion?: string}
     */
    public function interpretarRespuesta(array $campos, string $respuesta, array $valoresConocidos): array;
}
