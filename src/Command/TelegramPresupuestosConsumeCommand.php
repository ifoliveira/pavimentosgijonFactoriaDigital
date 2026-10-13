<?php

namespace App\Command;

use App\Repository\TelegramPresupuestoRecepcionRepository;
use App\Service\Telegram\TelegramBotClient;
use App\Service\Telegram\TelegramPresupuestoRecepcionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:telegram:presupuestos:consume', description: 'Consume fichas de visita recibidas por Telegram mediante polling.')]
final class TelegramPresupuestosConsumeCommand extends Command
{
    public function __construct(
        private readonly TelegramBotClient $telegram,
        private readonly TelegramPresupuestoRecepcionRepository $recepciones,
        private readonly TelegramPresupuestoRecepcionService $processor,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Procesa un lote de actualizaciones y termina.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->telegram->assertConfigured();
        $webhook = $this->telegram->getWebhookInfo();
        if (!empty($webhook['url'])) {
            $output->writeln('<error>El bot tiene un webhook activo. Elimínalo manualmente antes de usar polling.</error>');
            return Command::FAILURE;
        }

        $lock = $this->openLock();
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            $output->writeln('<error>Ya hay un consumidor Telegram ejecutándose para este proyecto.</error>');
            return Command::FAILURE;
        }

        $once = (bool) $input->getOption('once');
        do {
            $ultimo = $this->recepciones->ultimoUpdateProcesado($this->telegram->botId());
            $updates = $this->telegram->getUpdates($ultimo !== null ? $ultimo + 1 : null, $once ? 0 : 25);

            foreach ($updates as $update) {
                if (is_array($update)) {
                    $this->processor->procesarUpdate($update);
                }
            }

            if ($once) {
                break;
            }
        } while (true);

        return Command::SUCCESS;
    }

    private function openLock()
    {
        $dir = $this->projectDir.'/var/locks';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($dir.'/telegram-presupuestos-consume.lock', 'c');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el bloqueo del consumidor.');
        }

        return $handle;
    }
}
