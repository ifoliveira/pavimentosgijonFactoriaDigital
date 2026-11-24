<?php

namespace App\Service\Efectivo;

final class CajaImporte
{
    public static function centimos(mixed $valor, bool $permiteCero = false): int
    {
        if (!is_string($valor) || !preg_match('/^\d{1,7}(?:[.,]\d{1,2})?$/D', trim($valor))) {
            throw new \InvalidArgumentException('Indica un importe válido, sin separadores de miles y con un máximo de dos decimales.');
        }
        $partes = explode('.', str_replace(',', '.', trim($valor)));
        $centimos = (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
        if (!$permiteCero && $centimos === 0) {
            throw new \InvalidArgumentException('El importe debe ser mayor que cero.');
        }
        return $centimos;
    }
}
