<?php

namespace App\Service\WebPresupuesto;

final class WebPresupuestoCampoPath
{
    /**
     * @return array{componente: string, campo: string}|null
     */
    public function separar(string $path): ?array
    {
        $partes = explode('.', $path, 2);

        if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
            return null;
        }

        return [
            'componente' => $partes[0],
            'campo' => $partes[1],
        ];
    }

    public function construir(string $componente, string $campo): string
    {
        return $componente.'.'.$campo;
    }
}
