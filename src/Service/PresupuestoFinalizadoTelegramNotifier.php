<?php

namespace App\Service;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\Presupuestos;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PresupuestoFinalizadoTelegramNotifier
{
    public function __construct(
        private readonly TelegramNotifierService $telegramNotifier,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notificarDocumentoAceptado(Documento $presupuesto, ?Documento $documentoGenerado = null): void
    {
        try {
            $lineas = [
                '*Presupuesto aceptado*',
                '',
                sprintf('Presupuesto: %s', $this->escape($this->documentoLabel($presupuesto))),
                sprintf('Cliente: %s', $this->escape($this->clienteLabel($presupuesto->getCliente()))),
            ];

            $proyecto = $presupuesto->getProyecto();

            if ($proyecto !== null) {
                $lineas[] = sprintf('Proyecto: %s', $this->escape($proyecto->getNombre() ?? 'Sin nombre'));
            }

            if ($presupuesto->getTotal() !== '') {
                $lineas[] = sprintf('Total: %s', $this->escape($this->formatearImporte($presupuesto->getTotal())));
            }

            if ($documentoGenerado !== null && $documentoGenerado->getId() !== $presupuesto->getId()) {
                $lineas[] = sprintf('Generado: %s', $this->escape($this->documentoLabel($documentoGenerado)));
            }

            $lineas[] = '';
            $lineas[] = 'Abrir en ERP:';
            $lineas[] = $this->urlGenerator->generate(
                'app_documento_show',
                ['id' => $presupuesto->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $this->telegramNotifier->sendMessage(implode("\n", $lineas));
        } catch (\Throwable $e) {
            $this->logger->error('No se ha podido enviar el aviso Telegram de presupuesto aceptado.', [
                'exception' => $e,
                'documento_id' => $presupuesto->getId(),
            ]);
        }
    }

    public function notificarPresupuestoClasicoFinalizado(Presupuestos $presupuesto): void
    {
        try {
            $cliente = $presupuesto->getClientePe();
            $total = (float) $presupuesto->getImportetotPe() + (float) $presupuesto->getImportemanoobra();

            $lineas = [
                '*Presupuesto finalizado*',
                '',
                sprintf('Presupuesto: #%s', $this->escape((string) ($presupuesto->getId() ?? 'sin id'))),
                sprintf('Cliente: %s', $this->escape($this->clienteLabel($cliente))),
                sprintf('Estado: %s', $this->escape($presupuesto->getEstadoPe()?->getDescripcionEc() ?? 'Finalizado')),
                sprintf('Total: %s', $this->escape($this->formatearImporte((string) $total))),
            ];

            if ($cliente?->getDireccionCl()) {
                $lineas[] = sprintf('Direccion: %s', $this->escape($cliente->getDireccionCl()));
            }

            $lineas[] = '';
            $lineas[] = 'Abrir en ERP:';
            $lineas[] = $this->urlGenerator->generate(
                'presupuestos_show',
                ['id' => $presupuesto->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $this->telegramNotifier->sendMessage(implode("\n", $lineas));
        } catch (\Throwable $e) {
            $this->logger->error('No se ha podido enviar el aviso Telegram de presupuesto finalizado.', [
                'exception' => $e,
                'presupuesto_id' => $presupuesto->getId(),
            ]);
        }
    }

    private function documentoLabel(Documento $documento): string
    {
        if ($documento->getSerie() !== null && $documento->getNumero() !== null) {
            return $documento->getNumeroFormateado();
        }

        return '#' . ($documento->getId() ?? 'sin id');
    }

    private function clienteLabel(?Clientes $cliente): string
    {
        if ($cliente === null) {
            return 'Sin cliente';
        }

        $nombre = trim((string) $cliente);

        if ($nombre !== '') {
            return $nombre;
        }

        return $cliente->getEmailCl() ?: $cliente->getTelefono1Cl() ?: 'Sin nombre';
    }

    private function formatearImporte(?string $importe): string
    {
        if ($importe === null || !is_numeric($importe)) {
            return 'no disponible';
        }

        return number_format((float) $importe, 2, ',', '.') . ' EUR';
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', '_', '*', '[', ']', '(', ')'],
            ['\\\\', '\_', '\*', '\[', '\]', '\(', '\)'],
            $value
        );
    }
}
