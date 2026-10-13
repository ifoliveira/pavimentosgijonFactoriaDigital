<?php

namespace App\Tests\Service;

use App\Service\FichaVisita\FichaVisitaStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class FichaVisitaStorageTest extends TestCase
{
    public function testGuardaImagenComoJpegOptimizado(): void
    {
        $projectDir = sys_get_temp_dir().'/ficha-visita-storage-'.bin2hex(random_bytes(4));
        $sourcePath = $projectDir.'/source.png';

        mkdir($projectDir, 0775, true);
        $image = imagecreatetruecolor(3200, 1800);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        imagepng($image, $sourcePath);
        imagedestroy($image);

        $storage = new FichaVisitaStorage(new AsciiSlugger(), $projectDir);
        $filename = $storage->guardarTemporal(new UploadedFile($sourcePath, 'ficha.png', 'image/png', null, true));

        self::assertStringEndsWith('.jpg', $filename);
        self::assertSame('image/jpeg', $storage->mimeType($filename));

        [$width, $height] = getimagesize($storage->absolutePath($filename));
        self::assertSame(2400, max($width, $height));
    }
}
