<?php

namespace App\Service\Telegram;

final class TelegramAutorizacion
{
    /** @var string[] */
    private array $allowedUserIds;

    public function __construct(string $allowedUserIds)
    {
        $this->allowedUserIds = array_values(array_filter(array_map('trim', explode(',', $allowedUserIds)), static function (string $id): bool {
            return preg_match('/^\d+$/', $id) === 1;
        }));
    }

    public function tieneUsuariosConfigurados(): bool
    {
        return $this->allowedUserIds !== [];
    }

    public function permite(array $message): bool
    {
        $chatType = (string) ($message['chat']['type'] ?? '');
        $fromId = isset($message['from']['id']) ? (string) $message['from']['id'] : '';

        return $chatType === 'private'
            && $fromId !== ''
            && in_array($fromId, $this->allowedUserIds, true);
    }
}
