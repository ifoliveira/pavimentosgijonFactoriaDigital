<?php

namespace App\Tests\Service;

use App\Service\Telegram\TelegramAutorizacion;
use PHPUnit\Framework\TestCase;

final class TelegramAutorizacionTest extends TestCase
{
    public function testUsuarioAutorizadoYNoAutorizado(): void
    {
        $auth = new TelegramAutorizacion('123,456');

        self::assertTrue($auth->tieneUsuariosConfigurados());
        self::assertTrue($auth->permite(['chat' => ['type' => 'private'], 'from' => ['id' => 123]]));
        self::assertFalse($auth->permite(['chat' => ['type' => 'private'], 'from' => ['id' => 999]]));
        self::assertFalse($auth->permite(['chat' => ['type' => 'group'], 'from' => ['id' => 123]]));
    }

    public function testListaVaciaNoAutoriza(): void
    {
        $auth = new TelegramAutorizacion('');

        self::assertFalse($auth->tieneUsuariosConfigurados());
        self::assertFalse($auth->permite(['chat' => ['type' => 'private'], 'from' => ['id' => 123]]));
    }
}
