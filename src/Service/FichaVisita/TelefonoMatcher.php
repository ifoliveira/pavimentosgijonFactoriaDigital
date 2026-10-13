<?php

namespace App\Service\FichaVisita;

use App\Entity\Clientes;
use App\Repository\ClientesRepository;

final class TelefonoMatcher
{
    public function __construct(private readonly ClientesRepository $clientesRepository)
    {
    }

    /**
     * @return Clientes[]
     */
    public function buscarClientes(string $telefono): array
    {
        $normalizado = $this->normalizar($telefono);

        if ($normalizado === null) {
            return [];
        }

        $matches = [];

        foreach ($this->clientesRepository->findAll() as $cliente) {
            foreach ([$cliente->getTelefono1Cl(), $cliente->getTelefono2Cl()] as $telefonoCliente) {
                if ($telefonoCliente === null || trim($telefonoCliente) === '') {
                    continue;
                }

                $normalizadoCliente = $this->normalizar($telefonoCliente);

                if ($normalizadoCliente !== null && $this->sonEquivalentes($normalizado, $normalizadoCliente)) {
                    $matches[$cliente->getId() ?? spl_object_id($cliente)] = $cliente;
                    break;
                }
            }
        }

        return array_values($matches);
    }

    public function esValido(string $telefono): bool
    {
        return $this->normalizar($telefono) !== null;
    }

    public function normalizarParaGuardar(string $telefono): ?string
    {
        $normalizado = $this->normalizar($telefono);

        if ($normalizado === null) {
            return null;
        }

        if (str_starts_with($normalizado, '+34') && preg_match('/^\+34[6789]\d{8}$/', $normalizado)) {
            return substr($normalizado, 3);
        }

        return $normalizado;
    }

    public function sonEquivalentes(string $a, string $b): bool
    {
        $a = $this->normalizar($a);
        $b = $this->normalizar($b);

        if ($a === null || $b === null) {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        return $this->esEspanolEquivalente($a, $b) || $this->esEspanolEquivalente($b, $a);
    }

    private function normalizar(string $telefono): ?string
    {
        $telefono = trim($telefono);
        $telefono = str_replace([' ', '-', '(', ')', '.'], '', $telefono);

        if (str_starts_with($telefono, '0034')) {
            $telefono = '+34'.substr($telefono, 4);
        }

        if (preg_match('/^[6789]\d{8}$/', $telefono)) {
            return $telefono;
        }

        if (preg_match('/^\+[1-9]\d{6,14}$/', $telefono)) {
            return $telefono;
        }

        return null;
    }

    private function esEspanolEquivalente(string $internacional, string $nacional): bool
    {
        return str_starts_with($internacional, '+34')
            && preg_match('/^[6789]\d{8}$/', $nacional)
            && substr($internacional, 3) === $nacional;
    }
}
