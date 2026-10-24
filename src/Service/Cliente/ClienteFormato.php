<?php

namespace App\Service\Cliente;

final class ClienteFormato
{
    public const VIAS = [
        'Calle' => ['calle', 'c/', 'c.'],
        'Avenida' => ['avenida', 'avda.', 'avda', 'av.', 'av'],
        'Plaza' => ['plaza', 'pza.', 'pza', 'pl.'],
        'Camino' => ['camino', 'cno.', 'cno'],
        'Paseo' => ['paseo', 'pso.', 'pso'],
        'Carretera' => ['carretera', 'ctra.', 'ctra'],
        'Travesía' => ['travesía', 'travesia', 'trva.', 'trva'],
        'Ronda' => ['ronda'],
        'Urbanización' => ['urbanización', 'urbanizacion', 'urb.', 'urb'],
        'Lugar' => ['lugar'],
        'Barrio' => ['barrio'],
    ];

    public static function texto(string $value): string
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8');

        return preg_replace_callback('/(^|[\s\-\x{0027}\x{2019}])(\p{L})/u',
            static fn (array $match): string => $match[1] . mb_strtoupper($match[2], 'UTF-8'), $value);
    }

    public static function telefono(string $value): string
    {
        $value = trim($value);
        // Preserve extensions or other annotations rather than silently removing them.
        if (!preg_match('/^\+?[\d\s().-]*$/', $value)) {
            return $value;
        }
        $digits = preg_replace('/\D/', '', $value);

        return ($digits !== '' && str_starts_with($value, '+') ? '+' : '') . implode(' ', str_split($digits, 3));
    }

    public static function direccion(string $value, string $tipo = 'Calle'): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return '';
        }
        foreach (self::VIAS as $via => $aliases) {
            foreach ($aliases as $alias) {
                $pattern = '/^' . preg_quote($alias, '/') . (str_ends_with($alias, '/') || str_ends_with($alias, '.') ? '\s*' : '(?:\s+|$)') . '/iu';
                if (preg_match($pattern, $value)) {
                    return trim($via . ' ' . self::texto(preg_replace($pattern, '', $value, 1)));
                }
            }
        }

        return (array_key_exists($tipo, self::VIAS) ? $tipo : 'Calle') . ' ' . self::texto($value);
    }
}
