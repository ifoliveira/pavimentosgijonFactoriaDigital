<?php

namespace App\Tests\Service\Pdf;

use App\Service\Pdf\LocalImageDataUri;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LocalImageDataUriTest extends TestCase
{
    private string $directory;
    private string $image;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pdf-images-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        // A transparent PNG, deliberately with a misleading extension.
        $this->image = $this->directory . '/logo con espacios.jpg';
        copy(dirname(__DIR__, 3) . '/public/img/logo.png', $this->image);
    }

    protected function tearDown(): void
    {
        chmod($this->image, 0600);
        foreach (glob($this->directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testRelativeAbsoluteAndFileUrisPreserveExactImageBytes(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $service = new LocalImageDataUri($this->directory, $logger);
        $original = file_get_contents($this->image);
        foreach (['logo con espacios.jpg', $this->image, 'file://' . str_replace(' ', '%20', $this->image), 'file://localhost' . $this->image] as $path) {
            $uri = $service->convert($path);
            self::assertStringStartsWith('data:image/png;base64,', $uri);
            $decoded = base64_decode(explode(',', $uri, 2)[1], true);
            self::assertSame($original, $decoded, 'Pixels, dimensions and transparency must remain unchanged');
        }
    }

    /** @dataProvider invalidPaths */
    public function testUnavailableImagesAreLoggedAndOmitted(string $path): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'PDF image omitted: {reason}',
            self::callback(static fn (array $context): bool => $context['path'] === $path && $context['reason'] !== '')
        );
        file_put_contents($this->directory . '/corrupt.png', 'not an image');
        file_put_contents($this->directory . '/empty.png', '');
        self::assertNull((new LocalImageDataUri($this->directory, $logger))->convert($path));
    }

    public function invalidPaths(): iterable
    {
        foreach (['missing.png', 'corrupt.png', 'empty.png', '.', '', "bad\0.png", 'https://example.com/logo.png', 'file://remote/logo.png', 'data:image/png;base64,abc'] as $path) {
            yield [$path];
        }
    }

    public function testUnreadableImageIsLoggedAndOmitted(): void
    {
        chmod($this->image, 0000);
        clearstatcache(true, $this->image);
        if (is_readable($this->image)) {
            self::markTestSkipped('The current user can read files regardless of permission bits.');
        }
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        self::assertNull((new LocalImageDataUri($this->directory, $logger))->convert($this->image));
    }
}
