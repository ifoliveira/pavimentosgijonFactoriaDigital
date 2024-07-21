<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Email;
use PhpImap\Mailbox;

class EmailController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

 
        
    /**
     * @Route("/admin/fetch-emails", name="fetch_emails")
     */
    public function fetchEmails()
    {
        // Configuración del acceso a la cuenta de correo
        $mailbox = new Mailbox(
            '{mail.pavimentosgijon.es:993/imap/ssl}INBOX', // IMAP server and mailbox folder
            'buzon@pavimentosgijon.es', // Username for the before configured mailbox
            'Nacho$463', // Password for the before configured username
            __DIR__.'/../..', // Directory, where attachments will be saved (optional)
            'UTF-8' // Server encoding (optional)
        );

        try {
            // Obtener todos los correos no leídos
            $mailsIds = $mailbox->searchMailbox('UNSEEN');
            if (!$mailsIds) {
                echo 'Mailbox is empty';
                return $this->json(['status' => 'No new emails']);
            }

            foreach ($mailsIds as $mailId) {
                $mail = $mailbox->getMail($mailId);

                // Crear una nueva entidad Email y guardar la información
                $emailEntity = new Email();
                $emailEntity->setSubject($mail->subject);
                $emailEntity->setBody($mail->textHtml ?: $mail->textPlain);
                $emailEntity->setFromEmail($mail->fromAddress);
                $emailEntity->setIsRead(false);

                $this->entityManager->persist($emailEntity);
            }

            $this->entityManager->flush();

            // Marcar los correos como leídos
            $mailbox->markMailsAsRead($mailsIds);

            return $this->json(['status' => 'Emails fetched and saved']);
        } catch (\Exception $e) {
            return $this->json(['status' => 'Failed to fetch emails', 'error' => $e->getMessage()]);
        }
    }
}
?>