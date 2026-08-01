<?php

namespace App\Service\Documento;

use App\Entity\Documento;

class DescripcionPresupuestoFormatter
{
    public function formatear(string $texto): string
    {
        $texto = trim($texto);

        if ($texto === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $texto);

        $resultado = [];

        foreach ($tokens as $token) {

            // Medidas: 120X80, 60x60...
            if (preg_match('/^\d{2,4}[xX]\d{2,4}$/', $token)) {
                $resultado[] = strtoupper($token);
                continue;
            }

            // Códigos: DE100, FR633, WH20...
            if (preg_match('/^[A-Z]{1,5}\d+[A-Z0-9-]*$/i', $token)) {
                $resultado[] = strtoupper($token);
                continue;
            }

            // Siglas conocidas
            if (in_array(strtoupper($token), [
                'WH',
                'PVC',
                'LED',
                'ABS',
                'NPG',
                'ISO',
                'GAP',
                'BL',
            ], true)) {
                $resultado[] = strtoupper($token);
                continue;
            }

            $resultado[] = mb_strtolower($token, 'UTF-8');
        }

        $texto = implode(' ', $resultado);

        return mb_strtoupper(
            mb_substr($texto, 0, 1, 'UTF-8'),
            'UTF-8'
        ) . mb_substr($texto, 1, null, 'UTF-8');
    }
}