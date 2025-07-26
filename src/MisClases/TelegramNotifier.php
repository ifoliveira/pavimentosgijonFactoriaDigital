<?php


namespace App\MisClases;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramNotifier
{
    private string $botToken;
    private string $chatId;

    public function __construct()
    {
        $this->botToken = "7718189388:AAFGWOUtVpWhYQfi_0KAYHk9Hq2uNGc6iuM";
        $this->chatId = "7005366579";
    }

    public function sendMessage(string $message): void
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $data = [
            'chat_id' => $this->chatId,
            'text' => $message,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public function sendDocument(string $filePath, string $caption = ''): void
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendDocument";

        $postFields = [
            'chat_id' => $this->chatId,
            'caption' => $caption,
            'document' => new \CURLFile($filePath),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
