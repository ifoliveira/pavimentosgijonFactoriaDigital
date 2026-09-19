<?php

namespace App\Service\WebPresupuesto;

use App\Entity\PresupuestoWebInteraccion;
use App\Enum\TipoPresupuestoWebComunicacion;
use App\Enum\TipoPresupuestoWebInteraccion;
use App\Service\TelegramNotifierService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PresupuestoWebTelegramNotifier
{
    public function __construct(
        private readonly TelegramNotifierService $telegramNotifier,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function notificar(PresupuestoWebInteraccion $interaccion): void
    {
        if ($interaccion->getTipo() === TipoPresupuestoWebInteraccion::NO_CONTINUA) {
            return;
        }

        $this->telegramNotifier->sendMessage($this->mensaje($interaccion));
    }

    public function mensaje(PresupuestoWebInteraccion $interaccion): string
    {
        $presupuesto = $interaccion->getPresupuestoWeb();
        $lead = $presupuesto->getLead();
        $datos = $interaccion->getDatos() ?? [];
        $tipo = $interaccion->getTipo();

        $lineas = [
            $this->titulo($tipo),
            '',
            $this->escape($this->tipoPresupuestoLabel($presupuesto->getTipoPresupuesto())),
            sprintf('Importe%s: %s', $tipo === TipoPresupuestoWebInteraccion::CAMBIO ? ' actual' : '', $this->escape($this->formatearImporte($presupuesto->getTotal()))),
            sprintf('Fecha/hora: %s', $this->escape($this->formatearFecha($interaccion->getCreatedAt()))),
            '',
            sprintf('Email: %s', $this->escape($lead->getEmail())),
        ];

        $lineas = array_merge($lineas, $this->lineasDatos($tipo, $datos));

        $lineas[] = '';
        $lineas[] = sprintf(
            'Ha consultado el presupuesto %d %s.',
            $presupuesto->getNumeroVisualizaciones(),
            $presupuesto->getNumeroVisualizaciones() === 1 ? 'vez' : 'veces'
        );

        if ($presupuesto->getUltimaVisualizacionAt() !== null) {
            $lineas[] = sprintf('Última consulta: %s', $this->escape($this->formatearFecha($presupuesto->getUltimaVisualizacionAt())));
        }

        $origen = $this->origen($interaccion);

        if ($origen !== null) {
            $lineas[] = sprintf('Origen: %s', $this->escape($origen));
        }

        $lineas[] = '';
        $lineas[] = 'Abrir lead en ERP:';
        $lineas[] = $this->urlGenerator->generate(
            'admin_leads_web_show',
            ['id' => $presupuesto->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return implode("\n", $lineas);
    }

    private function titulo(TipoPresupuestoWebInteraccion $tipo): string
    {
        return match ($tipo) {
            TipoPresupuestoWebInteraccion::DUDA => '❓ DUDA SOBRE EL PRESUPUESTO',
            TipoPresupuestoWebInteraccion::CAMBIO => '🔄 QUIERE CAMBIAR ALGO',
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO => '📞 QUIERE QUE HABLEMOS',
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION => '🔥 QUIERE QUE VAYAMOS A MEDIR',
            TipoPresupuestoWebInteraccion::NO_CONTINUA => 'SEGUIMIENTO CERRADO',
        };
    }

    /**
     * @param array<string, mixed> $datos
     * @return string[]
     */
    private function lineasDatos(TipoPresupuestoWebInteraccion $tipo, array $datos): array
    {
        return match ($tipo) {
            TipoPresupuestoWebInteraccion::DUDA => $this->lineasDuda($datos),
            TipoPresupuestoWebInteraccion::CAMBIO => $this->lineasCambio($datos),
            TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO => $this->lineasContacto($datos),
            TipoPresupuestoWebInteraccion::SOLICITA_MEDICION => $this->lineasMedicion($datos),
            TipoPresupuestoWebInteraccion::NO_CONTINUA => [],
        };
    }

    private function lineasDuda(array $datos): array
    {
        $mensaje = $this->textoDato($datos, 'mensaje');

        return $mensaje === null ? [] : [
            '',
            'Pregunta:',
            sprintf('"%s"', $this->escape($mensaje)),
        ];
    }

    private function lineasCambio(array $datos): array
    {
        $componente = $this->textoDato($datos, 'componente');

        if ($componente === null) {
            return [];
        }

        return array_merge([
            '',
            'Quiere revisar:',
            $this->escape($this->nombreComponente($componente)),
        ], $this->lineasDatosExtra($datos, ['componente']));
    }

    private function lineasContacto(array $datos): array
    {
        $lineas = [];
        $telefono = $this->textoDato($datos, 'telefono');
        $franja = $this->textoDato($datos, 'franja');
        $mensaje = $this->textoDato($datos, 'mensaje');

        if ($telefono !== null) {
            $lineas[] = sprintf('Teléfono: %s', $this->escape($telefono));
        }

        if ($franja !== null) {
            $lineas[] = sprintf('Preferencia: %s', $this->escape($this->nombreFranja($franja)));
        }

        if ($mensaje !== null) {
            $lineas[] = '';
            $lineas[] = 'Mensaje:';
            $lineas[] = sprintf('"%s"', $this->escape($mensaje));
        }

        return $lineas;
    }

    private function lineasMedicion(array $datos): array
    {
        $lineas = [];
        $telefono = $this->textoDato($datos, 'telefono');
        $direccion = $this->textoDato($datos, 'direccion');
        $franja = $this->textoDato($datos, 'franja');
        $observaciones = $this->textoDato($datos, 'observaciones');

        if ($telefono !== null) {
            $lineas[] = sprintf('Teléfono: %s', $this->escape($telefono));
        }

        if ($direccion !== null) {
            $lineas[] = '';
            $lineas[] = 'Dirección:';
            $lineas[] = $this->escape($direccion);
        }

        if ($franja !== null) {
            $lineas[] = '';
            $lineas[] = sprintf('Preferencia: %s', $this->escape($this->nombreFranja($franja)));
        }

        if ($observaciones !== null) {
            $lineas[] = '';
            $lineas[] = 'Observaciones:';
            $lineas[] = sprintf('"%s"', $this->escape($observaciones));
        }

        return $lineas;
    }

    private function origen(PresupuestoWebInteraccion $interaccion): ?string
    {
        $comunicacion = $interaccion->getComunicacionOrigen();

        if ($comunicacion === null) {
            return null;
        }

        return match ($comunicacion->getTipo()) {
            TipoPresupuestoWebComunicacion::PRESUPUESTO => 'presupuesto inicial',
            TipoPresupuestoWebComunicacion::SEGUIMIENTO => 'seguimiento',
            TipoPresupuestoWebComunicacion::CIERRE => 'cierre',
        };
    }

    private function formatearImporte(?string $total): string
    {
        if ($total === null || !is_numeric($total)) {
            return 'no disponible';
        }

        return number_format((float) $total, 2, ',', '.').' €';
    }

    private function tipoPresupuestoLabel(string $tipo): string
    {
        return match ($tipo) {
            'ducha' => 'Cambio bañera → ducha',
            default => ucfirst(str_replace('_', ' ', $tipo)),
        };
    }

    private function formatearFecha(\DateTimeImmutable $fecha): string
    {
        return $fecha->setTimezone(new \DateTimeZone('Europe/Madrid'))->format('d/m/Y H:i');
    }

    private function textoDato(array $datos, string $clave): ?string
    {
        $valor = $datos[$clave] ?? null;

        if (!is_scalar($valor)) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function nombreComponente(string $componente): string
    {
        return match ($componente) {
            'plato' => 'Plato de ducha',
            'mampara' => 'Mampara',
            'griferia' => 'Grifería',
            default => $componente,
        };
    }

    private function nombreFranja(string $franja): string
    {
        return match ($franja) {
            'manana' => 'mañana',
            'tarde' => 'tarde',
            'indiferente' => 'me da igual',
            default => $franja,
        };
    }

    /**
     * @param array<string, mixed> $datos
     * @param string[] $excluir
     * @return string[]
     */
    private function lineasDatosExtra(array $datos, array $excluir): array
    {
        $lineas = [];

        foreach ($datos as $clave => $valor) {
            if (in_array($clave, $excluir, true) || !is_scalar($valor)) {
                continue;
            }

            $valor = trim((string) $valor);

            if ($valor === '') {
                continue;
            }

            if ($lineas === []) {
                $lineas[] = '';
                $lineas[] = 'Otros datos:';
            }

            $lineas[] = sprintf('%s: %s', $this->escape((string) $clave), $this->escape($valor));
        }

        return $lineas;
    }

    private function escape(string $text): string
    {
        return str_replace(
            ['\\', '*', '_', '`', '['],
            ['\\\\', '\\*', '\\_', '\\`', '\\['],
            $text
        );
    }
}
