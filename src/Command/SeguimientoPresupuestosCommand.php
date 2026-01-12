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

        // ==========================
        // EMAIL 2 (nuevo)
        // ==========================

        $fechaLimite2 = (new \DateTime())->modify('-3 days');

        $leadsEmail2 = $this->repo->createQueryBuilder('l')
            ->where('l.seguimientoActivo = true')
            ->andWhere('l.email1Enviado = true')
            ->andWhere('(l.email2Enviado IS NULL OR l.email2Enviado = false)')
            ->andWhere('l.ultimoEvento <= :fecha')
            ->setParameter('fecha', $fechaLimite2)
            ->getQuery()
            ->getResult();

        foreach ($leadsEmail2 as $lead) {

            $asunto = match ($lead->getTipoReforma()) {
                'ducha' => '¿Vemos el cambio de bañera por ducha?',
                'bano_completo' => '¿Comentamos la reforma del baño?',
                default => '¿Vemos el presupuesto con calma?',
            };

            $texto = match ($lead->getTipoReforma()) {
                'ducha' => 
                    "Hola {$lead->getNombre()},\n\n".
                    "Solo quería saber si has podido revisar con calma el presupuesto ".
                    "para el cambio de bañera por ducha.\n\n".
                    "Si te encaja, podemos comentar cualquier detalle: ".
                    "medidas, mampara, acabados o plazos, sin ningún compromiso.\n\n".
                    "Y si ahora no es el momento, no pasa nada, dímelo y lo dejamos apuntado.\n\n".
                    "Un saludo,\n".
                    "Nacho\n".
                    "Pavimentos Gijón",

                'baño_completo' =>
                    "Hola {$lead->getNombre()},\n\n".
                    "Quería saber si has tenido ocasión de revisar con calma ".
                    "el presupuesto para la reforma completa del baño.\n\n".
                    "Es normal que en este tipo de reformas surjan dudas, ".
                    "así que si quieres podemos ver opciones, ajustes o simplemente comentarlo ".
                    "sin ningún compromiso.\n\n".
                    "Cuando te venga bien, me dices.\n\n".
                    "Un saludo,\n".
                    "Nacho\n".
                    "Pavimentos Gijón",

                default =>
                    "Hola {$lead->getNombre()},\n\n".
                    "Solo quería saber si has podido revisar el presupuesto con calma ".
                    "y si te ha surgido alguna duda.\n\n".
                    "Si quieres, lo comentamos sin compromiso, ".
                    "o dime simplemente si ahora no es el momento.\n\n".
                    "Un saludo,\n".
                    "Nacho\n".
                    "Pavimentos Gijón",
            };


            $email = (new Email())
                ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
                ->to($lead->getEmail())
                ->subject($asunto)
                ->text($texto);

            $this->mailer->send($email);

            $lead->setEmail2Enviado(true);
            $lead->setUltimoEvento(new \DateTime());
        }

        // ==========================
        // EMAIL 3 (cierre del contacto)
        // ==========================

        $fechaLimite3 = (new \DateTime())->modify('-7 days');
   
        $leadsEmail3 = $this->repo->createQueryBuilder('l')
            ->where('l.seguimientoActivo = true')
            ->andWhere('l.email1Enviado = true')
            ->andWhere('l.email2Enviado = true')
            ->andWhere('l.ultimoEvento <= :fecha')
            ->setParameter('fecha', $fechaLimite3)
            ->getQuery()
            ->getResult();

        foreach ($leadsEmail3 as $lead) {

            if (!$lead->getEmail()) {
                continue;
            }

        $texto =
            "Hola {$lead->getNombre()},\n\n".
            "No te escribo para insistir 🙂\n\n".
            "No quería molestarte más, así que con este mensaje ".
            "cerramos el seguimiento del presupuesto.\n\n".
            "Si más adelante te surge cualquier duda, ".
            "o retomas la idea de la reforma, ".
            "puedes responder a este email o escribirnos ".
            "y lo vemos sin problema.\n\n".
            "Gracias por tu tiempo.\n\n".
            "Un saludo,\n".
            "Nacho\n".
            "Pavimentos Gijón";


            $email = (new Email())
                ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
                ->to($lead->getEmail())
                ->subject('Cerramos el seguimiento del presupuesto')
                ->text($texto);

            $this->mailer->send($email);

            // 🔒 CIERRE DEFINITIVO

            $lead->setSeguimientoActivo(false);
            $lead->setUltimoEvento(new \DateTime());
        }


        $this->em->flush();

        $output->writeln('✅ Seguimiento enviado primeros -'.count($leads).'-  segundos -' .count($leadsEmail2).'- y terceros -' .count($leadsEmail3).'- seguimietos de presupuestos.');

        return Command::SUCCESS;
    }
}
