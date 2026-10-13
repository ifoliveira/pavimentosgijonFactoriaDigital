<?php

namespace App\Service\FichaVisita;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class FichaVisitaStorage
{
    private const MAX_SIZE_BYTES = 25_165_824;
    private const MAX_DIMENSION_PX = 2400;
    private const JPEG_QUALITY = 82;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
    ) {
    }

    public function validar(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido por el servidor. Reduce la foto antes de subirla.',
                \UPLOAD_ERR_PARTIAL => 'La imagen se ha subido solo parcialmente. Vuelve a intentarlo.',
                \UPLOAD_ERR_NO_FILE => 'Sube una fotografía de la ficha para continuar.',
                default => $file->getErrorMessage(),
            };
        }

        if ($file->getSize() !== false && $file->getSize() > self::MAX_SIZE_BYTES) {
            return 'La imagen supera el tamaño máximo permitido de 24 MB.';
        }

        if (!in_array((string) $file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return 'La ficha debe ser una imagen JPG, PNG o WEBP.';
        }

        return null;
    }

    public function guardarTemporal(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'ficha-visita';
        $filename = strtolower((string) $this->slugger->slug($base)).'-'.bin2hex(random_bytes(8)).'.jpg';
        $temporalFilename = 'original-'.$filename;
        $mimeType = (string) $file->getMimeType();

        $file->move($this->absoluteBaseDir(), $temporalFilename);
        $temporalPath = $this->absolutePath($temporalFilename);
        $destinoPath = $this->absolutePath($filename);

        if (!$this->optimizarImagen($temporalPath, $mimeType, $destinoPath)) {
            rename($temporalPath, $destinoPath);
        } elseif (is_file($temporalPath)) {
            unlink($temporalPath);
        }

        return $filename;
    }

    public function mimeType(string $filename): string
    {
        $mimeType = mime_content_type($this->absolutePath($filename));

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : 'image/jpeg';
    }

    public function absolutePath(string $filename): string
    {
        $filename = basename($filename);

        return $this->absoluteBaseDir().DIRECTORY_SEPARATOR.$filename;
    }

    public function relativePath(string $filename): string
    {
        return 'var/fichas_visita/'.basename($filename);
    }

    private function absoluteBaseDir(): string
    {
        $dir = $this->projectDir.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'fichas_visita';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function optimizarImagen(string $origen, string $mimeType, string $destino): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $source = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($origen),
            'image/png' => @imagecreatefrompng($origen),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origen) : false,
            default => false,
        };

        if (!$source) {
            return false;
        }

        try {
            if ($mimeType === 'image/jpeg') {
                $source = $this->aplicarOrientacionExif($source, $origen);
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, self::MAX_DIMENSION_PX / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$target) {
                return false;
            }

            $white = imagecolorallocate($target, 255, 255, 255);
            imagefill($target, 0, 0, $white);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $saved = imagejpeg($target, $destino, self::JPEG_QUALITY);
            imagedestroy($target);

            return $saved;
        } finally {
            imagedestroy($source);
        }
    }

    private function aplicarOrientacionExif(\GdImage $source, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            3 => imagerotate($source, 180, 0) ?: $source,
            6 => imagerotate($source, -90, 0) ?: $source,
            8 => imagerotate($source, 90, 0) ?: $source,
            default => $source,
        };
    }
}
