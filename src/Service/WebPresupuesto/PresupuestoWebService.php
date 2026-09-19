<?php

namespace App\Service\WebPresupuesto;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebLead;
use App\Enum\EstadoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Repository\PresupuestoWebRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PresupuestoWebService
{
    private const ERROR_EMAIL = 'Introduce una dirección de correo electrónico válida.';
    private const ERROR_PRIVACIDAD = 'Debes confirmar que has leído la información sobre protección de datos.';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PresupuestoWebRepository $presupuestoWebRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly string $versionPrivacidad,
    ) {
    }

    public function crearDesdeSnapshot(string $email, mixed $privacidad, array $snapshot): array
    {
        $email = trim($email);

        if (!$this->emailValido($email)) {
            return [
                'ok' => false,
                'error' => self::ERROR_EMAIL,
            ];
        }

        if ($privacidad !== true) {
            return [
                'ok' => false,
                'error' => self::ERROR_PRIVACIDAD,
            ];
        }

        if (!$this->snapshotValido($snapshot)) {
            return [
                'ok' => false,
                'error' => 'No hemos encontrado el presupuesto generado. Vuelve a calcularlo antes de solicitar el envío.',
            ];
        }

        $resultado = $this->entityManager->getConnection()->transactional(function () use ($email, $snapshot): array {
            $now = new \DateTimeImmutable();
            $lead = new PresupuestoWebLead($email, $this->versionPrivacidad, $now);
            $presupuesto = new PresupuestoWeb(
                $lead,
                (string) ($snapshot['tipoPresupuesto'] ?? 'ducha'),
                $this->generarTokenUnico(),
                $snapshot['jsonSolicitudBudgetFlow'],
                $snapshot['jsonPresupuesto'],
                $this->normalizarTotal($snapshot['total'] ?? null),
                $now
            );
            $comunicacion = new PresupuestoWebComunicacion(
                $presupuesto,
                TipoPresupuestoWebComunicacion::PRESUPUESTO,
                EstadoPresupuestoWebComunicacion::PENDIENTE,
                $now
            );

            $this->entityManager->persist($lead);
            $this->entityManager->persist($presupuesto);
            $this->entityManager->persist($comunicacion);
            $this->entityManager->flush();

            return [
                'ok' => true,
                'lead' => $lead,
                'presupuesto' => $presupuesto,
                'comunicacion' => $comunicacion,
                'token' => $presupuesto->getToken(),
            ];
        });

        try {
            $this->enviarEmailInicial($resultado['lead'], $resultado['presupuesto'], $resultado['comunicacion']);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Hemos guardado tu presupuesto, pero no hemos podido enviar el email ahora mismo.',
            ];
        }

        $resultado['email_enviado'] = true;

        return $resultado;
    }

    private function emailValido(string $email): bool
    {
        if ($email === '' || strlen($email) > 255) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function snapshotValido(array $snapshot): bool
    {
        return is_array($snapshot['jsonSolicitudBudgetFlow'] ?? null)
            && is_array($snapshot['jsonPresupuesto'] ?? null)
            && ($snapshot['jsonSolicitudBudgetFlow'] ?? []) !== []
            && ($snapshot['jsonPresupuesto'] ?? []) !== [];
    }

    private function generarTokenUnico(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while ($this->presupuestoWebRepository->findOneBy(['token' => $token]) !== null);

        return $token;
    }

    private function normalizarTotal(mixed $total): ?string
    {
        if ($total === null || $total === '') {
            return null;
        }

        if (!is_numeric($total)) {
            return null;
        }

        return number_format((float) $total, 2, '.', '');
    }

    private function enviarEmailInicial(
        PresupuestoWebLead $lead,
        PresupuestoWeb $presupuesto,
        PresupuestoWebComunicacion $comunicacion
    ): void {
        $asunto = 'Todos los presupuestos se parecen. Hasta que empieza la obra.';
        $urlPresupuesto = $this->urlGenerator->generate(
            'web_presupuesto_ducha_ver',
            [
                'token' => $presupuesto->getToken(),
                'c' => $comunicacion->getTokenAcceso(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $htmlEmail = $this->twig->render('emails/presupuesto_web_ducha.html.twig', [
                'presupuesto' => $presupuesto,
                'lead' => $lead,
                'urlPresupuesto' => $urlPresupuesto,
                'total' => $presupuesto->getTotal(),
                'lineas' => $presupuesto->getJsonPresupuesto()['lineas'] ?? [],
            ]);

            $email = (new Email())
                ->from('Pavimentos Gijón <pavimentosgijon@gmail.com>')
                ->to($lead->getEmail())
                ->subject($asunto)
                ->html($htmlEmail);

            $this->mailer->send($email);
            $comunicacion->marcarEnviada($asunto, new \DateTimeImmutable());
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $comunicacion->marcarError($asunto, $e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }
}
