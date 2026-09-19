<?php

namespace App\Service\WebPresupuesto;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebAcceso;
use App\Entity\PresupuestoWebComunicacion;
use App\Repository\PresupuestoWebComunicacionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class PresupuestoWebAccesoService
{
    private const SESSION_NONCES = 'presupuesto_web_view_nonces';
    private const SESSION_DEDUPE = 'presupuesto_web_view_dedupe';
    private const DIRECTO = 'directo';
    private const NONCE_TTL_SECONDS = 600;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PresupuestoWebComunicacionRepository $comunicacionRepository,
        private readonly int $dedupeSeconds,
    ) {
    }

    public function resolverComunicacionOrigen(PresupuestoWeb $presupuesto, ?string $tokenAcceso): ?PresupuestoWebComunicacion
    {
        $tokenAcceso = trim((string) $tokenAcceso);

        if ($tokenAcceso === '') {
            return null;
        }

        $comunicacion = $this->comunicacionRepository->findOneBy(['tokenAcceso' => $tokenAcceso]);

        if (!$comunicacion instanceof PresupuestoWebComunicacion) {
            return null;
        }

        if ($comunicacion->getPresupuestoWeb()->getId() !== $presupuesto->getId()) {
            return null;
        }

        return $comunicacion;
    }

    public function generarViewNonce(SessionInterface $session, PresupuestoWeb $presupuesto, ?PresupuestoWebComunicacion $comunicacion): string
    {
        $nonce = bin2hex(random_bytes(32));
        $nonces = $this->noncesVigentes($session);
        $nonces[$nonce] = [
            'presupuesto_id' => $presupuesto->getId(),
            'comunicacion_id' => $comunicacion?->getId(),
            'creado' => time(),
        ];
        $session->set(self::SESSION_NONCES, $nonces);

        return $nonce;
    }

    /**
     * @return array{ok: bool, counted: bool, reason?: string}
     */
    public function registrarVisualizacion(SessionInterface $session, PresupuestoWeb $presupuesto, string $viewNonce): array
    {
        $nonces = $this->noncesVigentes($session);
        $nonceData = $nonces[$viewNonce] ?? null;
        unset($nonces[$viewNonce]);
        $session->set(self::SESSION_NONCES, $nonces);

        if (!is_array($nonceData)) {
            return ['ok' => false, 'counted' => false, 'reason' => 'nonce_invalido'];
        }

        if (($nonceData['presupuesto_id'] ?? null) !== $presupuesto->getId()) {
            return ['ok' => false, 'counted' => false, 'reason' => 'nonce_otro_presupuesto'];
        }

        $comunicacion = null;
        $comunicacionId = $nonceData['comunicacion_id'] ?? null;

        if ($comunicacionId !== null) {
            $comunicacion = $this->comunicacionRepository->find($comunicacionId);

            if (!$comunicacion instanceof PresupuestoWebComunicacion || $comunicacion->getPresupuestoWeb()->getId() !== $presupuesto->getId()) {
                return ['ok' => false, 'counted' => false, 'reason' => 'comunicacion_invalida'];
            }
        }

        $dedupeKey = $this->dedupeKey($presupuesto, $comunicacion);
        $dedupe = $this->dedupeVigente($session);

        if (isset($dedupe[$dedupeKey])) {
            return ['ok' => true, 'counted' => false, 'reason' => 'dedupe'];
        }

        $fechaAcceso = new \DateTimeImmutable();
        $this->entityManager->getConnection()->transactional(function () use ($presupuesto, $comunicacion, $fechaAcceso): void {
            $acceso = new PresupuestoWebAcceso($presupuesto, $comunicacion, $fechaAcceso);
            $presupuesto->registrarVisualizacion($fechaAcceso);

            $this->entityManager->persist($acceso);
            $this->entityManager->flush();
        });

        $dedupe[$dedupeKey] = time();
        $session->set(self::SESSION_DEDUPE, $dedupe);

        return ['ok' => true, 'counted' => true];
    }

    private function noncesVigentes(SessionInterface $session): array
    {
        $now = time();
        $nonces = $session->get(self::SESSION_NONCES, []);

        if (!is_array($nonces)) {
            return [];
        }

        return array_filter($nonces, static function (mixed $item) use ($now): bool {
            return is_array($item)
                && is_int($item['creado'] ?? null)
                && ($now - $item['creado']) <= self::NONCE_TTL_SECONDS;
        });
    }

    private function dedupeVigente(SessionInterface $session): array
    {
        $now = time();
        $dedupe = $session->get(self::SESSION_DEDUPE, []);

        if (!is_array($dedupe)) {
            return [];
        }

        return array_filter($dedupe, fn(mixed $timestamp): bool => is_int($timestamp) && ($now - $timestamp) < $this->dedupeSeconds);
    }

    private function dedupeKey(PresupuestoWeb $presupuesto, ?PresupuestoWebComunicacion $comunicacion): string
    {
        return sprintf(
            '%d:%s',
            $presupuesto->getId(),
            $comunicacion?->getId() ?? self::DIRECTO
        );
    }
}
