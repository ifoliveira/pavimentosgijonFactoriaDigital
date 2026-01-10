<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class TestEmailCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('app:test-email')
            ->setDescription('Prueba de envío de email');
    }

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('➡️ Antes de enviar');


        $email = (new Email())
            ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
            ->to('nacho.ifoliveira@gmail.com')
            ->subject('Prueba envío email Symfony')
            ->text('Si lees esto, el envío funciona 👍');


        $this->mailer->send($email);

        $output->writeln('✅ Después de enviar');

        $output->writeln('✅ Email enviado correctamente');

        return Command::SUCCESS;
    }
}
