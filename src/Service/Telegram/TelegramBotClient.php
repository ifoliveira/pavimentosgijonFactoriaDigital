<?php

namespace App\Service\Telegram;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramBotClient
{
    private const API_BASE = 'https://api.telegram.org';
    private const FILE_BASE = 'https://api.telegram.org/file';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $botToken,
    ) {
    }

    public function botId(): string
    {
        return hash('sha256', $this->botToken);
    }

    public function assertConfigured(): void
    {
        if (trim($this->botToken) === '') {
            throw new \RuntimeException('Configura TELEGRAM_PRESUPUESTOS_BOT_TOKEN antes de consumir mensajes de presupuestos.');
        }
    }

    public function getWebhookInfo(): array
    {
        return $this->request('GET', 'getWebhookInfo');
    }

    public function getMe(): array
    {
        return $this->request('GET', 'getMe');
    }

    public function getUpdates(?int $offset, int $timeout = 25): array
    {
        $body = [
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message'], JSON_THROW_ON_ERROR),
        ];

        if ($offset !== null) {
            $body['offset'] = $offset;
        }

        return $this->request('POST', 'getUpdates', $body);
    }

    public function getFile(string $fileId): array
    {
        return $this->request('POST', 'getFile', ['file_id' => $fileId]);
    }

    public function downloadFile(string $filePath): string
    {
        $url = sprintf('%s/bot%s/%s', self::FILE_BASE, $this->botToken, ltrim($filePath, '/'));
        $response = $this->client->request('GET', $url);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Telegram no ha permitido descargar el archivo.');
        }

        return $response->getContent();
    }

    public function sendMessage(string|int $chatId, string $text): void
    {
        $this->request('POST', 'sendMessage', [
            'chat_id' => (string) $chatId,
            'text' => $text,
            'disable_web_page_preview' => 'true',
        ]);
    }

    private function request(string $method, string $endpoint, array $body = []): array
    {
        $this->assertConfigured();
        $response = $this->client->request($method, sprintf('%s/bot%s/%s', self::API_BASE, $this->botToken, $endpoint), [
            'body' => $body,
        ]);

        $payload = $response->toArray(false);
        if (($payload['ok'] ?? false) !== true) {
            $description = is_string($payload['description'] ?? null) ? $payload['description'] : 'respuesta no válida';
            throw new \RuntimeException('Telegram API error: '.$description);
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }
}
