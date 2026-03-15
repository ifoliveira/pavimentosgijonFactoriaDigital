<?php
namespace App\Service;

class FinanciacionService
{
    private float $importe;
    private int $plazo;
    private int $modalidad;
    private float $financia3;

    public function __construct(float $importe, int $plazo)
    {
        $this->importe   = $importe;
        $this->plazo     = $plazo;
        $this->modalidad = 2;
        $this->financia3 = 0.03;
    }

    public function setModalidad(int $modalidad): self
    {
        $this->modalidad = $modalidad;
        return $this;
    }

    public function obtenerMensualidad(int $modal): float
    {
        if ($modal !== 0) {
            $this->setModalidad($modal);
        }

        return match ($this->modalidad) {
            2 => $this->importe / $this->plazo,
            3 => ($this->importe + ($this->importe * $this->financia3)) / $this->plazo,
            4 => $this->porcentajeInteres() === 9999.0
                    ? 9999
                    : $this->importe * $this->porcentajeInteres(),
            default => 0,
        };
    }

    public function costeComercio(int $modal): float
    {
        if ($modal !== 0) {
            $this->setModalidad($modal);
        }

        return match ($this->modalidad) {
            2, 3 => $this->importe * ($this->porcentajeInteres() / 100),
            default => 0,
        };
    }

    public function porcentajeInteres(): float
    {
        return match ($this->modalidad) {
            2 => match (true) {
                $this->plazo < 7  => 1.5,
                $this->plazo < 11 => 1.75,
                $this->plazo < 13 => 2.0,
                $this->plazo < 19 => 4.0,
                default           => 9999,
            },
            3 => match (true) {
                $this->plazo < 13 => 0.0,
                $this->plazo < 19 => 1.0,
                $this->plazo < 25 => 3.0,
                $this->plazo < 37 => 4.95,
                default           => 9999,
            },
            4 => match ($this->plazo) {
                3  => 0.33776,
                6  => 0.17055,
                9  => 0.11482,
                12 => 0.086965,
                18 => 0.059117,
                24 => 0.045204,
                36 => 0.031313,
                48 => 0.024389,
                60 => 0.020252,
                default => 9999,
            },
            default => 0,
        };
    }
}