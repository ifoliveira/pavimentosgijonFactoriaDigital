<?php

namespace App\Planos2D\Service;

final class PlanoPortableValidator
{
    private const VERSION = 1;
    private const DIRECCIONES = ['arriba', 'derecha', 'abajo', 'izquierda'];
    private const TIPOS_INTERIORES = ['plato_ducha', 'inodoro', 'bide', 'banera', 'mueble_lavabo', 'barra_ducha', 'grifo_higienico', 'radiador_toallero'];
    private const SENTIDOS_APERTURA = ['izquierda', 'derecha'];
    private const LADOS_APERTURA = ['interior', 'exterior'];

    public function validar(mixed $plano): array
    {
        if (!is_array($plano)) {
            throw new \InvalidArgumentException('El plano debe ser un objeto JSON.');
        }

        if (($plano['version'] ?? null) !== self::VERSION) {
            throw new \InvalidArgumentException('Version de plano no soportada. Se esperaba version 1.');
        }

        foreach (['muros', 'puertas', 'ventanas', 'elementos'] as $campo) {
            if (!array_key_exists($campo, $plano) || !is_array($plano[$campo])) {
                throw new \InvalidArgumentException(sprintf('El campo %s debe ser un array.', $campo));
            }
        }

        $muros = array_map(fn (mixed $muro, int $index): array => $this->validarMuro($muro, $index), $plano['muros'], array_keys($plano['muros']));
        $this->validarIdsUnicos($muros, 'muros');
        $murosPorId = [];

        foreach ($muros as $muro) {
            $murosPorId[$muro['id']] = $muro;
        }

        $puertas = array_map(fn (mixed $puerta, int $index): array => $this->validarPuerta($puerta, $index, $murosPorId), $plano['puertas'], array_keys($plano['puertas']));
        $ventanas = array_map(fn (mixed $ventana, int $index): array => $this->validarVentana($ventana, $index, $murosPorId), $plano['ventanas'], array_keys($plano['ventanas']));
        $elementos = array_map(fn (mixed $elemento, int $index): array => $this->validarInterior($elemento, $index, $murosPorId), $plano['elementos'], array_keys($plano['elementos']));

        $this->validarIdsUnicos(array_merge($puertas, $ventanas), 'puertas y ventanas');
        $this->validarIdsUnicos($elementos, 'elementos interiores');

        return [
            'version' => self::VERSION,
            'muros' => $muros,
            'puertas' => $puertas,
            'ventanas' => $ventanas,
            'elementos' => $elementos,
        ];
    }

    private function validarMuro(mixed $muro, int $index): array
    {
        $this->exigirObjeto($muro, "muros[$index]");
        $id = $this->validarTexto($muro['id'] ?? null, "muros[$index].id");
        $direccion = $muro['direccion'] ?? null;

        if (!is_string($direccion) || !in_array($direccion, self::DIRECCIONES, true)) {
            throw new \InvalidArgumentException("Direccion no valida en muros[$index].");
        }

        return [
            'id' => $id,
            'longitud' => $this->validarNumeroPositivo($muro['longitud'] ?? null, "muros[$index].longitud"),
            'direccion' => $direccion,
            'auxiliar' => ($muro['auxiliar'] ?? false) === true,
        ];
    }

    private function validarPuerta(mixed $puerta, int $index, array $murosPorId): array
    {
        $base = $this->validarHueco($puerta, $index, 'puertas', $murosPorId);
        $sentidoApertura = $puerta['sentidoApertura'] ?? null;
        $ladoApertura = $puerta['ladoApertura'] ?? null;

        if (!is_string($sentidoApertura) || !in_array($sentidoApertura, self::SENTIDOS_APERTURA, true)) {
            throw new \InvalidArgumentException("Sentido de apertura no valido en puertas[$index].");
        }

        if (!is_string($ladoApertura) || !in_array($ladoApertura, self::LADOS_APERTURA, true)) {
            throw new \InvalidArgumentException("Lado de apertura no valido en puertas[$index].");
        }

        return $base + [
            'sentidoApertura' => $sentidoApertura,
            'ladoApertura' => $ladoApertura,
        ];
    }

    private function validarVentana(mixed $ventana, int $index, array $murosPorId): array
    {
        return $this->validarHueco($ventana, $index, 'ventanas', $murosPorId);
    }

    private function validarHueco(mixed $hueco, int $index, string $coleccion, array $murosPorId): array
    {
        $this->exigirObjeto($hueco, "$coleccion[$index]");
        $id = $this->validarTexto($hueco['id'] ?? null, "$coleccion[$index].id");
        $muroId = $this->validarTexto($hueco['muroId'] ?? null, "$coleccion[$index].muroId");

        if (!isset($murosPorId[$muroId])) {
            throw new \InvalidArgumentException("$coleccion[$index] referencia un muro inexistente.");
        }

        $distanciaDesdeInicio = $this->validarNumeroNoNegativo($hueco['distanciaDesdeInicio'] ?? null, "$coleccion[$index].distanciaDesdeInicio");
        $ancho = $this->validarNumeroPositivo($hueco['ancho'] ?? null, "$coleccion[$index].ancho");

        if ($distanciaDesdeInicio + $ancho > $murosPorId[$muroId]['longitud']) {
            throw new \InvalidArgumentException("$coleccion[$index] no cabe dentro de su muro.");
        }

        return [
            'id' => $id,
            'muroId' => $muroId,
            'distanciaDesdeInicio' => $distanciaDesdeInicio,
            'ancho' => $ancho,
        ];
    }

    private function validarInterior(mixed $elemento, int $index, array $murosPorId): array
    {
        $this->exigirObjeto($elemento, "elementos[$index]");
        $tipo = $elemento['tipo'] ?? null;

        if (!is_string($tipo) || !in_array($tipo, self::TIPOS_INTERIORES, true)) {
            throw new \InvalidArgumentException("Tipo de elemento interior no valido en elementos[$index].");
        }

        $normalizado = [
            'id' => $this->validarTexto($elemento['id'] ?? null, "elementos[$index].id"),
            'tipo' => $tipo,
            'x' => $this->validarNumeroFinito($elemento['x'] ?? null, "elementos[$index].x"),
            'y' => $this->validarNumeroFinito($elemento['y'] ?? null, "elementos[$index].y"),
            'ancho' => $this->validarNumeroPositivo($elemento['ancho'] ?? null, "elementos[$index].ancho"),
            'fondo' => $this->validarNumeroPositivo($elemento['fondo'] ?? null, "elementos[$index].fondo"),
            'rotacion' => $this->normalizarRotacion($this->validarNumeroFinito($elemento['rotacion'] ?? null, "elementos[$index].rotacion")),
        ];

        if (array_key_exists('muroId', $elemento)) {
            $normalizado['muroId'] = $this->validarTexto($elemento['muroId'], "elementos[$index].muroId");

            if (!array_key_exists($normalizado['muroId'], $murosPorId)) {
                throw new \InvalidArgumentException("elementos[$index].muroId referencia un muro inexistente.");
            }
        }

        return $normalizado;
    }

    private function exigirObjeto(mixed $valor, string $nombre): void
    {
        if (!is_array($valor) || array_is_list($valor)) {
            throw new \InvalidArgumentException("$nombre debe ser un objeto.");
        }
    }

    private function validarTexto(mixed $valor, string $nombre): string
    {
        if (!is_string($valor) || trim($valor) === '') {
            throw new \InvalidArgumentException("$nombre debe ser un texto no vacio.");
        }

        return trim($valor);
    }

    private function validarNumeroPositivo(mixed $valor, string $nombre): float|int
    {
        $numero = $this->validarNumeroFinito($valor, $nombre);

        if ($numero <= 0) {
            throw new \InvalidArgumentException("$nombre debe ser mayor que 0.");
        }

        return $numero;
    }

    private function validarNumeroNoNegativo(mixed $valor, string $nombre): float|int
    {
        $numero = $this->validarNumeroFinito($valor, $nombre);

        if ($numero < 0) {
            throw new \InvalidArgumentException("$nombre debe ser mayor o igual que 0.");
        }

        return $numero;
    }

    private function validarNumeroFinito(mixed $valor, string $nombre): float|int
    {
        if (!is_int($valor) && !is_float($valor)) {
            throw new \InvalidArgumentException("$nombre debe ser un numero valido.");
        }

        if (!is_finite((float) $valor)) {
            throw new \InvalidArgumentException("$nombre debe ser un numero valido.");
        }

        return $valor;
    }

    private function validarIdsUnicos(array $elementos, string $nombre): void
    {
        $ids = array_map(static fn (array $elemento): string => $elemento['id'], $elementos);

        if (count(array_unique($ids)) !== count($ids)) {
            throw new \InvalidArgumentException("El plano contiene ids duplicados en $nombre.");
        }
    }

    private function normalizarRotacion(float|int $rotacion): float|int
    {
        $normalizada = fmod((float) $rotacion, 360.0);

        if ($normalizada < 0) {
            $normalizada += 360.0;
        }

        return (int) $normalizada == $normalizada ? (int) $normalizada : $normalizada;
    }
}
