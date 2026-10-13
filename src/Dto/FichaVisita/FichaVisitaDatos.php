<?php

namespace App\Dto\FichaVisita;

final class FichaVisitaDatos
{
    public function __construct(
        public readonly ?string $nombreCliente,
        public readonly ?string $telefono,
        public readonly ?string $direccionObra,
        public readonly array $pendientes = [],
        public readonly array $tecnicos = [],
        public readonly array $casillas = [],
        public readonly array $contradicciones = [],
        public readonly array $observaciones = [],
        public readonly array $original = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $pendientes = $data['pendientes'] ?? [];

        return new self(
            self::limpiar($data['nombre_cliente'] ?? $data['nombreCliente'] ?? null),
            self::limpiar($data['telefono'] ?? null),
            self::limpiar($data['direccion_obra'] ?? $data['direccionObra'] ?? null),
            is_array($pendientes) ? array_values(array_filter($pendientes, 'is_string')) : [],
            self::arrayValue($data['tecnicos'] ?? $data['datos_tecnicos'] ?? []),
            self::arrayValue($data['casillas'] ?? []),
            self::stringList($data['contradicciones'] ?? []),
            self::stringList($data['observaciones'] ?? []),
            $data
        );
    }

    public function toArray(): array
    {
        return [
            'nombre_cliente' => $this->nombreCliente,
            'telefono' => $this->telefono,
            'direccion_obra' => $this->direccionObra,
            'pendientes' => $this->pendientes,
            'tecnicos' => $this->tecnicos,
            'casillas' => $this->casillas,
            'contradicciones' => $this->contradicciones,
            'observaciones' => $this->observaciones,
            'original' => $this->original,
        ];
    }

    private static function limpiar(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
