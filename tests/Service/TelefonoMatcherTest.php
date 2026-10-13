<?php

namespace App\Tests\Service;

use App\Service\FichaVisita\TelefonoMatcher;
use PHPUnit\Framework\TestCase;

final class TelefonoMatcherTest extends TestCase
{
    public function testSpanishNationalAndPlus34AreEquivalent(): void
    {
        $matcher = new TelefonoMatcher($this->createClientesRepository());

        self::assertTrue($matcher->sonEquivalentes('600 111 222', '+34 600-111-222'));
        self::assertSame('600111222', $matcher->normalizarParaGuardar('+34 600 111 222'));
    }

    public function testInternationalPrefixesRemainDifferent(): void
    {
        $matcher = new TelefonoMatcher($this->createClientesRepository());

        self::assertFalse($matcher->sonEquivalentes('+33 600111222', '+34 600111222'));
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $matcher = new TelefonoMatcher($this->createClientesRepository());

        self::assertFalse($matcher->esValido('REF-12345'));
    }

    private function createClientesRepository(): \App\Repository\ClientesRepository
    {
        return $this->getMockBuilder(\App\Repository\ClientesRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAll'])
            ->getMock();
    }
}
