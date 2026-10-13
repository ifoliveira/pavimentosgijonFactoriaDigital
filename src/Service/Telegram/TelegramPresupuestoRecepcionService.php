<?php

namespace App\Service\Telegram;

use App\Entity\Documento;
use App\Entity\TelegramPresupuestoRecepcion;
use App\Repository\TelegramPresupuestoRecepcionRepository;
use App\Service\FichaVisita\FichaVisitaAiExtractorInterface;
use App\Service\FichaVisita\FichaVisitaStorage;
use App\Service\FichaVisita\PresupuestoDesdeFotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TelegramPresupuestoRecepcionService
{
    private const MAX_FILE_BYTES = 10_485_760;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly TelegramBotClient $telegram,
        private readonly TelegramAutorizacion $autorizacion,
        private readonly TelegramPresupuestoRecepcionRepository $recepciones,
        private readonly FichaVisitaStorage $storage,
        private readonly FichaVisitaAiExtractorInterface $extractor,
        private readonly PresupuestoDesdeFotoService $presupuestoDesdeFotoService,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $erpPublicBaseUrl,
    ) {
    }

    public function procesarUpdate(array $update): void
    {
        $updateId = (int) ($update['update_id'] ?? -1);
        if ($updateId < 0) {
            return;
        }

        $message = is_array($update['message'] ?? null) ? $update['message'] : null;
        if ($message === null) {
            $this->registrarRechazada($updateId, 0, 0, 'Actualización sin mensaje.');
            return;
        }

        $chatId = (string) ($message['chat']['id'] ?? '0');
        $messageId = (int) ($message['message_id'] ?? 0);
        $recepcion = $this->obtenerORegistrarRecepcion($updateId, $chatId, $messageId, $message);

        if ($recepcion->getEstado() !== TelegramPresupuestoRecepcion::ESTADO_RECIBIDA) {
            if ($recepcion->getEstado() === TelegramPresupuestoRecepcion::ESTADO_NOTIFICACION_PENDIENTE) {
                $this->notificarResultadoExistente($recepcion);
            }
            return;
        }

        if (!$this->autorizacion->tieneUsuariosConfigurados()) {
            $this->rechazar($recepcion, 'No hay usuarios autorizados configurados.');
            return;
        }

        if (!$this->autorizacion->permite($message)) {
            $this->rechazar($recepcion, 'Usuario o chat no autorizado.');
            return;
        }

        if (isset($message['text'])) {
            $this->responderTexto($recepcion, trim((string) $message['text']));
            return;
        }

        if (isset($message['media_group_id'])) {
            $this->rechazarConMensaje($recepcion, 'En esta fase envía una sola ficha por mensaje, no un álbum.');
            return;
        }

        try {
            $imagen = $this->descargarImagen($message);
        } catch (\Throwable $e) {
            $this->rechazarConMensaje($recepcion, $e->getMessage());
            return;
        }

        $recepcion->setImagenPath($this->storage->relativePath($imagen['filename']));
        $this->em->flush();

        try {
            $this->telegram->sendMessage($recepcion->getChatId(), 'Ficha recibida. Estoy preparando el presupuesto.');
        } catch (\Throwable) {
            // La notificacion final queda controlada de forma idempotente; este acuse no debe parar la generacion.
        }

        try {
            $datos = $this->extractor->extraer($this->storage->absolutePath($imagen['filename']), $this->storage->mimeType($imagen['filename']))->toArray();
            $recepcion->setDatosExtraidos($datos);
            $this->em->flush();

            $resultado = $this->presupuestoDesdeFotoService->ejecutar(
                $datos,
                'Cambio de bañera por ducha',
                '',
                is_string($datos['direccion_obra'] ?? null) ? $datos['direccion_obra'] : null,
                $recepcion->getImagenPath()
            );

            $this->aplicarResultado($recepcion, $resultado);
            $this->notificarResultadoExistente($recepcion);
        } catch (\Throwable $e) {
            $recepcion
                ->setEstado(TelegramPresupuestoRecepcion::ESTADO_ERROR)
                ->setErrorResumen($e->getMessage());
            $this->em->flush();
            $this->telegram->sendMessage($recepcion->getChatId(), 'No he podido preparar el presupuesto. Revisa la recepción en el ERP o vuelve a intentarlo más tarde.');
        }
    }

    /**
     * @return array{filename: string}
     */
    private function descargarImagen(array $message): array
    {
        $fileId = null;
        $fileSize = null;
        $filename = 'ficha-telegram.jpg';

        if (isset($message['photo']) && is_array($message['photo'])) {
            $photos = array_values(array_filter($message['photo'], 'is_array'));
            if ($photos === []) {
                throw new \RuntimeException('No he podido leer la fotografía enviada.');
            }
            usort($photos, static fn (array $a, array $b): int => (int) ($b['file_size'] ?? 0) <=> (int) ($a['file_size'] ?? 0));
            $fileId = (string) ($photos[0]['file_id'] ?? '');
            $fileSize = isset($photos[0]['file_size']) ? (int) $photos[0]['file_size'] : null;
        } elseif (isset($message['document']) && is_array($message['document'])) {
            $document = $message['document'];
            $fileId = (string) ($document['file_id'] ?? '');
            $fileSize = isset($document['file_size']) ? (int) $document['file_size'] : null;
            $filename = basename((string) ($document['file_name'] ?? $filename));
        }

        if ($fileId === null || $fileId === '') {
            throw new \RuntimeException('Envía una fotografía o una imagen JPG/PNG como documento.');
        }

        if ($fileSize !== null && $fileSize > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('La imagen supera el límite de 10 MB. Reduce la foto y vuelve a enviarla.');
        }

        $fileInfo = $this->telegram->getFile($fileId);
        if (isset($fileInfo['file_size']) && (int) $fileInfo['file_size'] > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('La imagen supera el límite de 10 MB de descarga.');
        }

        $contents = $this->telegram->downloadFile((string) ($fileInfo['file_path'] ?? ''));
        if (strlen($contents) > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('La imagen descargada supera el límite de 10 MB.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'telegram-ficha-');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo preparar el archivo recibido.');
        }
        file_put_contents($tmp, $contents);

        $mime = mime_content_type($tmp) ?: '';
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            @unlink($tmp);
            throw new \RuntimeException('Formato no admitido. Envía una imagen JPEG o PNG.');
        }

        $uploaded = new UploadedFile($tmp, $filename, $mime, null, true);
        $error = $this->storage->validar($uploaded);
        if ($error !== null) {
            @unlink($tmp);
            throw new \RuntimeException($error);
        }

        return ['filename' => $this->storage->guardarTemporal($uploaded)];
    }

    private function aplicarResultado(TelegramPresupuestoRecepcion $recepcion, array $resultado): void
    {
        $recepcion->setDatosExtraidos($resultado['datos'] ?? $recepcion->getDatosExtraidos());
        $recepcion->setAdaptacion($resultado['adaptacion'] ?? null);
        $recepcion->setProyecto($resultado['proyecto'] ?? null);
        $recepcion->setDocumento($resultado['documento'] ?? null);

        $recepcion->setEstado(match ($resultado['status']) {
            PresupuestoDesdeFotoService::COMPLETADO => TelegramPresupuestoRecepcion::ESTADO_COMPLETADA,
            PresupuestoDesdeFotoService::PENDIENTE_CLIENTE, PresupuestoDesdeFotoService::CLIENTE_AMBIGUO => TelegramPresupuestoRecepcion::ESTADO_PENDIENTE_CLIENTE,
            PresupuestoDesdeFotoService::REVISION_TECNICA => TelegramPresupuestoRecepcion::ESTADO_REVISION_TECNICA,
            default => TelegramPresupuestoRecepcion::ESTADO_ERROR,
        });

        $recepcion->setErrorResumen($resultado['warning'] ?? null);
        $this->em->flush();
    }

    private function notificarResultadoExistente(TelegramPresupuestoRecepcion $recepcion): void
    {
        try {
            $this->telegram->sendMessage($recepcion->getChatId(), $this->mensajeResultado($recepcion));
            $recepcion->marcarNotificada();
            if ($recepcion->getEstado() === TelegramPresupuestoRecepcion::ESTADO_NOTIFICACION_PENDIENTE) {
                $recepcion->setEstado($recepcion->getDocumento() ? TelegramPresupuestoRecepcion::ESTADO_COMPLETADA : TelegramPresupuestoRecepcion::ESTADO_PENDIENTE_CLIENTE);
            }
            $this->em->flush();
        } catch (\Throwable $e) {
            $recepcion
                ->setEstado(TelegramPresupuestoRecepcion::ESTADO_NOTIFICACION_PENDIENTE)
                ->setErrorResumen('Fallo notificando Telegram: '.$e->getMessage());
            $this->em->flush();
        }
    }

    private function mensajeResultado(TelegramPresupuestoRecepcion $recepcion): string
    {
        $documento = $recepcion->getDocumento();
        if ($recepcion->getEstado() === TelegramPresupuestoRecepcion::ESTADO_PENDIENTE_CLIENTE || !$documento instanceof Documento) {
            return "Faltan datos de cliente para crear el presupuesto.\nCompleta la recepción aquí:\n".$this->url('app_proyecto_desde_foto_telegram_completar', ['id' => $recepcion->getId()]);
        }

        if ($recepcion->getEstado() === TelegramPresupuestoRecepcion::ESTADO_REVISION_TECNICA || $recepcion->getEstado() === TelegramPresupuestoRecepcion::ESTADO_ERROR) {
            return "La ficha necesita revisión antes de terminar el presupuesto.\n".$this->url('app_proyecto_desde_foto_revisar', ['id' => $documento->getId()]);
        }

        $cliente = $documento->getCliente();
        $proyecto = $documento->getProyecto();

        return implode("\n", [
            'Presupuesto preparado.',
            'Cliente: '.trim((string) ($cliente?->getNombreCl().' '.$cliente?->getApellidosCl())),
            'Obra: '.(string) $proyecto?->getNombre(),
            'Presupuesto: '.$documento->getNumeroFormateado(),
            'Total con IVA: '.number_format((float) $documento->getTotal(), 2, ',', '.').' €',
            'Abrir en ERP: '.$this->url('app_documento_show', ['id' => $documento->getId()]),
        ]);
    }

    private function responderTexto(TelegramPresupuestoRecepcion $recepcion, string $text): void
    {
        $mensaje = match (strtolower(strtok($text, ' ') ?: $text)) {
            '/start' => 'Envía una foto de la ficha de visita para preparar el presupuesto.',
            '/help' => 'Puedes enviar una única imagen JPG o PNG, como foto o documento. No envíes álbumes ni PDF en esta fase.',
            default => 'Envía una fotografía de la ficha de visita. Los textos no ejecutan el flujo.',
        };

        $recepcion->setEstado(TelegramPresupuestoRecepcion::ESTADO_RECHAZADA);
        $this->em->flush();
        $this->telegram->sendMessage($recepcion->getChatId(), $mensaje);
    }

    private function rechazarConMensaje(TelegramPresupuestoRecepcion $recepcion, string $mensaje): void
    {
        $this->rechazar($recepcion, $mensaje);
        $this->telegram->sendMessage($recepcion->getChatId(), $mensaje);
    }

    private function rechazar(TelegramPresupuestoRecepcion $recepcion, string $motivo): void
    {
        $recepcion
            ->setEstado(TelegramPresupuestoRecepcion::ESTADO_RECHAZADA)
            ->setErrorResumen($motivo);
        $this->em->flush();
    }

    private function obtenerORegistrarRecepcion(int $updateId, string $chatId, int $messageId, array $message): TelegramPresupuestoRecepcion
    {
        $botId = $this->telegram->botId();
        $recepcion = $this->recepciones->findOneByBotAndUpdate($botId, $updateId);
        if ($recepcion instanceof TelegramPresupuestoRecepcion) {
            return $recepcion;
        }

        $recepcion = new TelegramPresupuestoRecepcion($botId, $updateId, $chatId, $messageId);
        $recepcion
            ->setFromUserId($message['from']['id'] ?? null)
            ->setFromUsername(isset($message['from']['username']) ? (string) $message['from']['username'] : null)
            ->setUltimoUpdateProcesado($updateId);
        $this->em->persist($recepcion);
        $this->em->flush();

        return $recepcion;
    }

    private function registrarRechazada(int $updateId, string|int $chatId, int $messageId, string $motivo): void
    {
        $recepcion = $this->obtenerORegistrarRecepcion($updateId, (string) $chatId, $messageId, []);
        $this->rechazar($recepcion, $motivo);
    }

    private function url(string $route, array $parameters = []): string
    {
        $path = $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);

        return rtrim($this->erpPublicBaseUrl, '/').$path;
    }
}
