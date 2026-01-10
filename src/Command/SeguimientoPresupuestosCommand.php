<?php


namespace App\Command;

use App\Repository\PresupuestosLeadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SeguimientoPresupuestosCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('app:seguimiento-presupuestos')
            ->setDescription('Envía el primer email de seguimiento a presupuestos descargados');
    }

    private PresupuestosLeadRepository $repo;
    private MailerInterface $mailer;
    private EntityManagerInterface $em;

    public function __construct(
        PresupuestosLeadRepository $repo,
        MailerInterface $mailer,
        EntityManagerInterface $em
    ) {
        parent::__construct();
        $this->repo = $repo;
        $this->mailer = $mailer;
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fechaLimite = (new \DateTime())->modify('-24 hours');

        $leads = $this->repo->createQueryBuilder('l')
            ->where('l.seguimientoActivo = true')
            ->andWhere('(l.email1Enviado IS NULL OR l.email1Enviado = false)')
            ->andWhere('l.fechaPdf <= :fecha')
            ->setParameter('fecha', $fechaLimite)
            ->getQuery()
            ->getResult();

        if (count($leads) === 0) {
            $output->writeln('ℹ️ No hay presupuestos pendientes de seguimiento');
            return Command::SUCCESS;
        }

        foreach ($leads as $lead) {

            // Seguridad mínima
            if (!$lead->getEmail()) {
                continue;
            }

            $email = (new Email())
                ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
                ->to($lead->getEmail())
                ->subject('¿Tienes alguna duda sobre tu presupuesto?')
                ->text(
                    "Hola {$lead->getNombre()},\n\n".
                    "Ayer te enviamos el presupuesto y queríamos saber si te ha surgido alguna duda.\n\n".
                    "Si quieres, podemos comentarlo sin compromiso.\n\n".
                    "Un saludo,\n".
                    "Pavimentos Gijón"
                );

            $this->mailer->send($email);

            $lead->setEmail1Enviado(true);
            $lead->setUltimoEvento(new \DateTime());
        }

        $this->em->flush();

        $output->writeln('✅ Seguimiento enviado a '.count($leads).' presupuestos');

        return Command::SUCCESS;
    }
}
