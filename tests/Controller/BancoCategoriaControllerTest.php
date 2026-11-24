<?php

namespace App\Tests\Controller;

use App\Controller\BancoController;
use App\Entity\Banco;
use App\Entity\Tiposmovimiento;
use App\Repository\TiposmovimientoRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BancoCategoriaControllerTest extends TestCase
{
    /** @dataProvider casos */
    public function testGuardarCategoria(bool $token, ?string $id, bool $existe, int $status): void
    {
        $original = (new Tiposmovimiento())->setDescripcionTm('Original');
        $nueva = (new Tiposmovimiento())->setDescripcionTm('Nueva');
        $banco = (new Banco())->setCategoriaBn($original)->setConceptoBn('Extracto')->setImporteBn(42);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($status === 200 ? self::once() : self::never())->method('flush');
        $controller = $this->getMockBuilder(BancoController::class)
            ->setConstructorArgs([$em])->onlyMethods(['isCsrfTokenValid'])->getMock();
        $controller->expects(self::once())->method('isCsrfTokenValid')
            ->with('banco_categoria_', 'token')->willReturn($token);
        $tipos = $this->createMock(TiposmovimientoRepository::class);
        $consulta = $token && $id !== null && ctype_digit($id) && (int) $id > 0;
        $tipos->expects($consulta ? self::once() : self::never())->method('find')->willReturn($existe ? $nueva : null);

        $response = $controller->categoria(new Request([], ['_token' => 'token', 'categoria' => $id]), $banco, $tipos);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($status !== 200 ? $original : ($id === '' ? null : $nueva), $banco->getCategoriaBn());
        self::assertSame('Extracto', $banco->getConceptoBn());
        self::assertSame(42.0, $banco->getImporteBn());
        if ($status === 200) {
            self::assertSame($id === '' ? 'Sin categoría' : 'Nueva', json_decode($response->getContent(), true)['descripcion']);
        }
    }

    public function casos(): iterable
    {
        yield 'guardar existente' => [true, '2', true, 200];
        yield 'quitar categoria' => [true, '', false, 200];
        yield 'token invalido' => [false, '2', true, 403];
        yield 'categoria inexistente' => [true, '99', false, 422];
        yield 'valor invalido' => [true, 'abc', false, 422];
        yield 'sin parametro' => [true, null, false, 422];
        yield 'cero' => [true, '0', false, 422];
    }
}
