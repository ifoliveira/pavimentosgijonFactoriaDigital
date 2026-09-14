<?php

namespace App\Integration\BudgetFlow;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BudgetFlowClient
{
    public function __construct(
        private readonly HttpClientInterface $budgetFlowClient,
    ) {
    }

    public function obtenerConfigurador(string $codigo): array
    {
        $response = $this->budgetFlowClient->request(
            'GET',
            sprintf(
                '/api/v1/configuradores/%s',
                urlencode($codigo)
            )
        );

        return $response->toArray();
    }

    public function listarConfiguradores(): array
    {
        $response = $this->budgetFlowClient->request(
            'GET',
            '/api/v1/configuradores'
        );

        return $response->toArray();
    }

    public function validar(string $codigo, array $datos): array
    {
        $response = $this->budgetFlowClient->request(
            'POST',
            sprintf(
                '/api/v1/configuradores/%s/validar',
                urlencode($codigo)
            ),
            [
                'json' => $datos,
            ]
        );

        return $response->toArray();
    }

    public function generar(string $codigo, array $datos): array
    {

        $response = $this->budgetFlowClient->request(
            'POST',
            sprintf(
                '/api/v1/configuradores/%s/generar',
                urlencode($codigo)
            ),
            [
                'json' => $datos,
            ]
        );

        return $response->toArray();
    }

}
