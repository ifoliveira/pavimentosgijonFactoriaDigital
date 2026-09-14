<?php

namespace App\Planos2D\DTO;

final class PlanoPdfData
{
    public function __construct(
        public readonly ?string $cliente = null,
        public readonly ?string $direccion = null,
        public readonly ?string $referencia = null,
        public readonly ?string $titulo = 'PLANO DE DISTRIBUCIÓN',
        public readonly ?string $subtitulo = 'Reforma de baño',
        public readonly ?\DateTimeImmutable $fecha = null,
        public readonly bool $planoParcial = false,
    ) {
    }

    public static function fromArray(array $datos): self
    {
        return new self(
            self::textoOpcional($datos['cliente'] ?? null),
            self::textoOpcional($datos['direccion'] ?? null),
            self::textoOpcional($datos['referencia'] ?? null),
            self::textoOpcional($datos['titulo'] ?? null) ?: 'PLANO DE DISTRIBUCIÓN',
            self::textoOpcional($datos['subtitulo'] ?? null) ?: 'Reforma de baño',
            self::fechaOpcional($datos['fecha'] ?? null),
            ($datos['planoParcial'] ?? false) === true,
        );
    }

    public function fechaGeneracion(): \DateTimeImmutable
    {
        return $this->fecha ?? new \DateTimeImmutable();
    }

    private static function textoOpcional(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : mb_substr($valor, 0, 160);
    }

    private static function fechaOpcional(mixed $valor): ?\DateTimeImmutable
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($valor);
        } catch (\Exception) {
            throw new \InvalidArgumentException('La fecha del PDF no es valida.');
        }
    }
}
