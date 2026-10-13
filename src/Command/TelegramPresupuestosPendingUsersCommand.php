<?php

namespace App\Command;

use App\Service\Telegram\TelegramBotClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:telegram:presupuestos:pending-users', description: 'Muestra usuarios pendientes de Telegram sin consumir actualizaciones.')]
final class TelegramPresupuestosPendingUsersCommand extends Command
{
    public function __construct(private readonly TelegramBotClient $telegram)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->telegram->assertConfigured();
        $updates = $this->telegram->getUpdates(null, 0);
        $seen = [];

        foreach ($updates as $update) {
            if (!is_array($update) || !is_array($update['message'] ?? null)) {
                continue;
            }

            $message = $update['message'];
            $userId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if ($userId === null || $chatId === null) {
                continue;
            }

            $key = $userId.'-'.$chatId;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $output->writeln(sprintf('user_id=%s chat_id=%s', $userId, $chatId));
        }

        if ($seen === []) {
            $output->writeln('No hay usuarios pendientes. Envía /start al bot y vuelve a ejecutar el comando.');
        }

        return Command::SUCCESS;
    }
}
