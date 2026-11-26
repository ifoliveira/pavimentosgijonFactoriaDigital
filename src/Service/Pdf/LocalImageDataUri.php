<?php

namespace App\Service\Pdf;

use Psr\Log\LoggerInterface;

/** Embeds local raster images without resizing, re-encoding or losing alpha. */
final class LocalImageDataUri
{
    public function __construct(
        private readonly string $projectDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Relative paths are relative to kernel.project_dir, never the working directory. */
    public function convert(string $path): ?string
    {
        $source = $path;
        if (str_starts_with($path, 'file://')) {
            $parts = parse_url($path);
            if ($parts === false || !isset($parts['path']) || !in_array($parts['host'] ?? '', ['', 'localhost'], true)) {
                return $this->unavailable($source, 'Invalid local file URI');
            }
            $path = rawurldecode($parts['path']);
        }

        if ($path === '' || str_contains($path, "\0") || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) {
            return $this->unavailable($source, 'Expected a local image path');
        }

        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir . '/' . $path;
        }

        if (!is_file($path) || !is_readable($path)) {
            return $this->unavailable($source, 'Image does not exist or is not readable');
        }

        // A file may disappear or become unreadable between the check and the read.
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return $this->unavailable($source, 'Image could not be read');
        }

        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/webp'], true)) {
            return $this->unavailable($source, 'Invalid or unsupported raster image');
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function unavailable(string $path, string $reason): ?string
    {
        $this->logger->warning('PDF image omitted: {reason}', ['path' => $path, 'reason' => $reason]);

        return null;
    }
}
