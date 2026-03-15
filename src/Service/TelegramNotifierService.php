<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramNotifierService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $botToken,
        private string $chatId,
    ) {}

    public function sendMessage(string $message): void
    {
        $this->client->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'body' => [
                'chat_id'    => $this->chatId,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ],
        ]);
    }

    public function sendDocument(string $filePath, string $caption = ''): void
    {
        $this->client->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendDocument", [
            'body' => [
                'chat_id' => $this->chatId,
                'caption' => $caption,
            ],
            'extra' => [
                'curl' => [
                    CURLOPT_POSTFIELDS => [
                        'chat_id'  => $this->chatId,
                        'caption'  => $caption,
                        'document' => new \CURLFile($filePath),
                    ],
                ],
            ],
        ]);
    }
}