<?php

namespace App\Service;

use App\Entity\PresupuestosLead;
use App\Repository\PresupuestosLeadRepository;
use App\Service\PresupuestoCalculatorService;
use App\Service\TelegramNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class PresupuestoLeadService
{
    private EntityManagerInterface $em;
    private PresupuestosLeadRepository $leadRepo;
    private PresupuestoCalculatorService $calculator;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private TelegramNotifierService $notifier;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        PresupuestosLeadRepository $leadRepo,
        PresupuestoCalculatorService $calculator,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        TelegramNotifierService $notifier,
        Environment $twig
    ) {
        $this->em = $em;
        $this->leadRepo = $leadRepo;
        $this->calculator = $calculator;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->notifier = $notifier;
        $this->twig = $twig;
    }

    public function guardarLeadConEmail(string $nombre, string $email, string $tipo, array $json): void
    {
        $estimacion = $this->calculator->calcular($tipo, $json);
        $min = $estimacion['min'];
        $max = $estimacion['max'];
        $rangoTexto = number_format($min, 0, ',', '.') . ' € – ' . number_format($max, 0, ',', '.') . ' €';

        $lead = $this->leadRepo->findOneBy(['email' => $email]) ?? new PresupuestosLead();
        $token = $lead->getToken() ?? bin2hex(random_bytes(24));

        $lead->setNombre($nombre);
        $lead->setEmail($email);
        $lead->setTipoReforma($tipo);
        $lead->setJsonPresupuesto($json);
        $lead->setToken($token);
        $lead->setPdfDescargas(0);
        $lead->setUltimoEvento(new \DateTime());
        $lead->setTotal($estimacion['total']);
        $lead->setFechaPdf(new \DateTime());
        $lead->setSeguimientoActivo(true);
        $lead->setEmail1Enviado(false);
        $lead->setEmail2Enviado(false);

        $this->em->persist($lead);
        $this->em->flush();

        $urlPdf = $this->urlGenerator->generate(
            'presupuesto_descargar',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $htmlEmail = $this->twig->render('emails/presupuesto.html.twig', [
            'nombre' => $nombre,
            'rangoTexto' => $rangoTexto,
            'min' => $min,
            'max' => $max,
            'tipo' => $tipo,
            'urlPdf' => $urlPdf,
        ]);

        $asunto = match ($tipo) {
            'ducha' => 'Tu estimación · Cambio de bañera por ducha en Gijón',
            default => 'Tu estimación · Reforma integral de baño en Gijón',
        };

        $emailMessage = (new Email())
            ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
            ->to($email)
            ->subject($asunto)
            ->html($htmlEmail);

        $this->mailer->send($emailMessage);
        $this->notifier->sendMessage("📩 Nuevo lead: {$nombre} | {$email} | {$tipo} | {$estimacion['total']} €");
    }

    public function iniciarLead(string $tipo, array $json): array
    {
        $res = $this->calculator->calcular($tipo, $json);
        $token = bin2hex(random_bytes(24));

        $lead = new PresupuestosLead();
        $lead->setTipoReforma($tipo);
        $lead->setJsonPresupuesto($json);
        $lead->setTotal($res['total']);
        $lead->setManoObra(array_sum($res['mano_obra']));
        $lead->setMateriales(array_sum($res['materiales']));
        $lead->setToken($token);
        $lead->setFechaPdf(new \DateTime());
        $lead->setSeguimientoActivo(false);
        $lead->setUltimoEvento(new \DateTime());

        $this->em->persist($lead);
        $this->em->flush();

        return [
            'token' => $token,
            'url' => $this->urlGenerator->generate(
                'presupuesto_ver',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }

    public function completarLead(string $token, string $nombre, string $email): void
    {
        $lead = $this->leadRepo->findOneBy(['token' => $token]);
        if (!$lead) {
            throw new \RuntimeException('Lead no encontrado');
        }

        $lead->setNombre($nombre);
        $lead->setEmail($email);
        $lead->setSeguimientoActivo(true);
        $lead->setEmail1Enviado(false);
        $lead->setEmail2Enviado(false);
        $lead->setFechaPdf(new \DateTime());
        $lead->setUltimoEvento(new \DateTime());
        $this->em->flush();

        $json = $lead->getJsonPresupuesto();
        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        $res = $this->calculator->calcular($lead->getTipoReforma(), $json);
        $total = (int) $res['total'];
        $min = (int) ($total * 0.92);
        $max = (int) ($total * 1.08);
        $rangoTexto = number_format($min, 0, ',', '.') . ' € – ' . number_format($max, 0, ',', '.') . ' €';

        $urlPdf = $this->urlGenerator->generate(
            'presupuesto_pdf',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $htmlEmail = $this->twig->render('emails/presupuesto.html.twig', [
            'nombre' => $nombre,
            'rangoTexto' => $rangoTexto,
            'min' => $min,
            'max' => $max,
            'tipo' => $lead->getTipoReforma(),
            'urlPdf' => $urlPdf,
        ]);

        $asunto = match ($lead->getTipoReforma()) {
            'ducha' => 'Tu estimación · Cambio de bañera por ducha en Gijón',
            default => 'Tu estimación · Reforma integral de baño en Gijón',
        };

        $emailMsg = (new Email())
            ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
            ->to($email)
            ->subject($asunto)
            ->html($htmlEmail);

        $this->mailer->send($emailMsg);
        $this->notifier->sendMessage("📩 Lead completado: {$nombre} | {$email} | {$lead->getTipoReforma()} | {$total} €");
    }
}
