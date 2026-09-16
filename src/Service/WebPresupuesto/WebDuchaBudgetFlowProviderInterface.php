<?php

namespace App\Service\WebPresupuesto;

interface WebDuchaBudgetFlowProviderInterface
{
    public function obtenerConfigurador(): array;

    public function validar(array $configurador, array $valores): array;

    public function generar(array $configurador, array $valores): array;
}
