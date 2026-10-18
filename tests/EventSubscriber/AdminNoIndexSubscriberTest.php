<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\AdminNoIndexSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AdminNoIndexSubscriberTest extends TestCase
{
    /** @dataProvider adminResponses */
    public function testAdminResponsesCannotBeIndexed(string $uri, int $status): void
    {
        $response = new Response('', $status, ['X-Robots-Tag' => 'index']);
        $this->dispatch($uri, $response);

        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function adminResponses(): array
    {
        return [
            ['/admin', 200],
            ['/admin/', 302],
            ['/admin/clientes?page=2', 200],
            ['/admin/documento/1', 403],
            ['/admin/inexistente', 404],
            ['/admin/dashboard', 500],
        ];
    }

    public function testPublicPagesRemainIndexable(): void
    {
        foreach (['/', '/contacto', '/sitemap.xml'] as $uri) {
            $response = new Response();
            $this->dispatch($uri, $response);
            self::assertFalse($response->headers->has('X-Robots-Tag'));
        }
    }

    public function testExistingPublicPageRestrictionIsPreserved(): void
    {
        $response = new Response('', 200, ['X-Robots-Tag' => 'noindex']);
        $this->dispatch('/privado', $response);
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    private function dispatch(string $uri, Response $response): void
    {
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($uri),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
        (new AdminNoIndexSubscriber())->onResponse($event);
    }
}
