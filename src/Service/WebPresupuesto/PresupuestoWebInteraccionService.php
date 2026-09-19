<?php

namespace App\Service\WebPresupuesto;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebComunicacion;
use App\Entity\PresupuestoWebInteraccion;
use App\Enum\TipoPresupuestoWebInteraccion;
use App\Repository\PresupuestoWebComunicacionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PresupuestoWebInteraccionService
{
    private const FRANJAS_VALIDAS = ['manana', 'tarde', 'indiferente'];
    private const COMPONENTES_VALIDOS = ['plato', 'mampara', 'griferia'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PresupuestoWebComunicacionRepository $comunicacionRepository,
        private readonly PresupuestoWebTelegramNotifier $telegramNotifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, interaccion?: PresupuestoWebInteraccion, seguimientoDetenido?: bool, comunicacionesCanceladas?: int}
     */
    public function registrar(PresupuestoWeb $presupuesto, array $payload): array
    {
        $tipo = $this->resolverTipo($payload['tipo'] ?? null);

        if (!$tipo instanceof TipoPresupuestoWebInteraccion) {
            return ['ok' => false, 'error' => 'Acción no válida.'];
        }

        $datos = $this->normalizarDatos($tipo, is_array($payload['datos'] ?? null) ? $payload['datos'] : []);

        if (($datos['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => $datos['error'] ?? 'Datos no válidos.'];
        }

        $comunicacionOrigen = $this->resolverComunicacionOrigen(
            $presupuesto,
            isset($payload['comunicacionToken']) ? (string) $payload['comunicacionToken'] : null
        );
        $detenerSeguimiento = $this->detieneSeguimiento($tipo);
        $datosInteraccion = $datos['datos'] === [] ? null : $datos['datos'];
        $comunicacionesCanceladas = 0;

        $interaccion = $this->entityManager->getConnection()->transactional(function () use (
            $presupuesto,
            $comunicacionOrigen,
            $tipo,
            $datosInteraccion,
            $detenerSeguimiento,
            &$comunicacionesCanceladas
        ): PresupuestoWebInteraccion {
            $interaccion = new PresupuestoWebInteraccion($presupuesto, $comunicacionOrigen, $tipo, $datosInteraccion);
            $this->entityManager->persist($interaccion);

            if ($detenerSeguimiento) {
                $presupuesto->getLead()->setSeguimientoActivo(false);

                foreach ($this->comunicacionRepository->findPendientesParaPresupuesto($presupuesto) as $comunicacion) {
                    $comunicacion->marcarCancelada();
                    $comunicacionesCanceladas++;
                }
            }

            $this->entityManager->flush();

            return $interaccion;
        });

        if ($tipo !== TipoPresupuestoWebInteraccion::NO_CONTINUA) {
            try {
                $this->telegramNotifier->notificar($interaccion);
            } catch (\Throwable $e) {
                $this->logger->error('No se ha podido enviar el aviso Telegram de interacción de presupuesto web.', [
                    'exception' => $e,
                    'tipo' => $tipo->value,
                    'presupuesto_id' => $presupuesto->getId(),
                ]);
            }
        }

        return [
            'ok' => true,
            'interaccion' => $interaccion,
            'seguimientoDetenido' => $detenerSeguimiento,
            'comunicacionesCanceladas' => $comunicacionesCanceladas,
        ];
    }

    private function resolverTipo(mixed $tipo): ?TipoPresupuestoWebInteraccion
    {
        if (!is_string($tipo)) {
            return null;
        }

        return TipoPresupuestoWebInteraccion::tryFrom($tipo);
    }

    /**
     * @return array{ok: bool, datos?: array<string, string>, error?: string}
     */
    private function normalizarDatos(TipoPresupuestoWebInteraccion $tipo, array $datos): array
    {
        return match ($tipo) {
            TipoPresupuestoWebInteraccion::DUDA => $this->normalizarDuda($datos),
            TipoPresupuestoWebInteraccion::CAMBIO => $this->normalizarCambio($datos),
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO => $this->normalizarContacto($datos),
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION => $this->normalizarMedicion($datos),
            TipoPresupuestoWebInteraccion::NO_CONTINUA => ['ok' => true, 'datos' => []],
        };
    }

    private function normalizarDuda(array $datos): array
    {
        $mensaje = $this->limpiarTexto($datos['mensaje'] ?? '');

        if ($mensaje === '') {
            return ['ok' => false, 'error' => 'Cuéntanos qué quieres saber.'];
        }

        return ['ok' => true, 'datos' => ['mensaje' => $mensaje]];
    }

    private function normalizarCambio(array $datos): array
    {
        $componente = $this->limpiarTexto($datos['componente'] ?? '');

        if (!in_array($componente, self::COMPONENTES_VALIDOS, true)) {
            return ['ok' => false, 'error' => 'Elige qué quieres cambiar.'];
        }

        return ['ok' => true, 'datos' => ['componente' => $componente]];
    }

    private function normalizarContacto(array $datos): array
    {
        $telefono = $this->limpiarTexto($datos['telefono'] ?? '');
        $franja = $this->normalizarFranja($datos['franja'] ?? '');

        if (!$this->telefonoValido($telefono)) {
            return ['ok' => false, 'error' => 'Introduce un teléfono válido.'];
        }

        if ($franja === null) {
            return ['ok' => false, 'error' => 'Elige cuándo te viene mejor.'];
        }

        $resultado = [
            'telefono' => $telefono,
            'franja' => $franja,
        ];
        $mensaje = $this->limpiarTexto($datos['mensaje'] ?? '');

        if ($mensaje !== '') {
            $resultado['mensaje'] = $mensaje;
        }

        return ['ok' => true, 'datos' => $resultado];
    }

    private function normalizarMedicion(array $datos): array
    {
        $telefono = $this->limpiarTexto($datos['telefono'] ?? '');
        $direccion = $this->limpiarTexto($datos['direccion'] ?? '');
        $franja = $this->normalizarFranja($datos['franja'] ?? '');

        if (!$this->telefonoValido($telefono)) {
            return ['ok' => false, 'error' => 'Introduce un teléfono válido.'];
        }

        if ($direccion === '') {
            return ['ok' => false, 'error' => 'Indica la dirección donde se realizará la obra.'];
        }

        if ($franja === null) {
            return ['ok' => false, 'error' => 'Elige cuándo te viene mejor.'];
        }

        $resultado = [
            'telefono' => $telefono,
            'direccion' => $direccion,
            'franja' => $franja,
        ];
        $observaciones = $this->limpiarTexto($datos['observaciones'] ?? '');

        if ($observaciones !== '') {
            $resultado['observaciones'] = $observaciones;
        }

        return ['ok' => true, 'datos' => $resultado];
    }

    private function resolverComunicacionOrigen(PresupuestoWeb $presupuesto, ?string $tokenAcceso): ?PresupuestoWebComunicacion
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

    private function detieneSeguimiento(TipoPresupuestoWebInteraccion $tipo): bool
    {
        return in_array($tipo, [
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO,
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION,
            TipoPresupuestoWebInteraccion::NO_CONTINUA,
        ], true);
    }

    private function limpiarTexto(mixed $valor): string
    {
        if (!is_scalar($valor)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');
    }

    private function normalizarFranja(mixed $franja): ?string
    {
        $franja = $this->limpiarTexto($franja);

        return in_array($franja, self::FRANJAS_VALIDAS, true) ? $franja : null;
    }

    private function telefonoValido(string $telefono): bool
    {
        if (strlen($telefono) < 9 || strlen($telefono) > 20) {
            return false;
        }

        return preg_match('/^\+?[0-9\s().-]{9,20}$/', $telefono) === 1;
    }
}
