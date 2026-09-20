<?php

namespace App\Integration\BudgetFlow;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class BudgetFlowClient
{
    public function __construct(
        private readonly HttpClientInterface $budgetFlowClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function obtenerConfigurador(string $codigo): array
    {
        return $this->request(
            'GET',
            sprintf(
                '/api/v1/configuradores/%s',
                urlencode($codigo)
            )
        );
    }

    public function listarConfiguradores(): array
    {
        return $this->request(
            'GET',
            '/api/v1/configuradores'
        );
    }

    public function validar(string $codigo, array $datos): array
    {
        return $this->request(
            'POST',
            sprintf(
                '/api/v1/configuradores/%s/validar',
                urlencode($codigo)
            ),
            [
                'json' => $datos,
            ]
        );
    }

    public function generar(string $codigo, array $datos): array
    {

        return $this->request(
            'POST',
            sprintf(
                '/api/v1/configuradores/%s/generar',
                urlencode($codigo)
            ),
            [
                'json' => $datos,
            ]
        );
    }

    private function request(string $method, string $endpoint, array $options = []): array
    {
        $payload = is_array($options['json'] ?? null) ? $options['json'] : [];

        try {
            $response = $this->budgetFlowClient->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            $this->logger()->error('Error de transporte llamando a BudgetFlow.', [
                'endpoint' => $endpoint,
                'method' => $method,
                'payload' => $payload,
                'exception' => $e,
            ]);

            throw new BudgetFlowException(
                sprintf("Error de transporte llamando a BudgetFlow.\n\nEndpoint:\n%s\n\nError:\n%s", $endpoint, $e->getMessage()),
                $endpoint,
                null,
                null,
                $payload,
                $e
            );
        } catch (\Throwable $e) {
            $this->logger()->error('Error inesperado llamando a BudgetFlow.', [
                'endpoint' => $endpoint,
                'method' => $method,
                'payload' => $payload,
                'exception' => $e,
            ]);

            throw new BudgetFlowException(
                sprintf("Error inesperado llamando a BudgetFlow.\n\nEndpoint:\n%s\n\nError:\n%s", $endpoint, $e->getMessage()),
                $endpoint,
                null,
                null,
                $payload,
                $e
            );
        }

        if ($statusCode >= 400) {
            $this->logger()->error('BudgetFlow respondió con error HTTP.', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => $statusCode,
                'payload' => $payload,
                'response_body' => $body,
            ]);

            throw new BudgetFlowException(
                sprintf(
                    "BudgetFlow respondió HTTP %d\n\nEndpoint:\n%s\n\nRespuesta:\n%s",
                    $statusCode,
                    $endpoint,
                    $body !== '' ? $body : '(respuesta vacía)'
                ),
                $endpoint,
                $statusCode,
                $body,
                $payload
            );
        }

        if (trim($body) === '') {
            $this->logger()->error('BudgetFlow devolvió una respuesta vacía.', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => $statusCode,
                'payload' => $payload,
            ]);

            throw new BudgetFlowException(
                sprintf("BudgetFlow devolvió una respuesta vacía.\n\nEndpoint:\n%s", $endpoint),
                $endpoint,
                $statusCode,
                $body,
                $payload
            );
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger()->error('BudgetFlow devolvió una respuesta no JSON.', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => $statusCode,
                'payload' => $payload,
                'response_body' => $body,
                'exception' => $e,
            ]);

            throw new BudgetFlowException(
                sprintf("BudgetFlow devolvió una respuesta no JSON.\n\nEndpoint:\n%s\n\nRespuesta:\n%s", $endpoint, $body),
                $endpoint,
                $statusCode,
                $body,
                $payload,
                $e
            );
        }

        if (!is_array($decoded)) {
            $this->logger()->error('BudgetFlow devolvió un JSON con formato no esperado.', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => $statusCode,
                'payload' => $payload,
                'response_body' => $body,
            ]);

            throw new BudgetFlowException(
                sprintf("BudgetFlow devolvió un JSON con formato no esperado.\n\nEndpoint:\n%s\n\nRespuesta:\n%s", $endpoint, $body),
                $endpoint,
                $statusCode,
                $body,
                $payload
            );
        }

        return $decoded;
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
