<?php

namespace App\Service\WebPresupuesto;

final class WebDuchaDebugContext
{
    private static ?string $traceId = null;

    public static function setTraceId(?string $traceId): void
    {
        self::$traceId = $traceId;
    }

    public static function getTraceId(): ?string
    {
        return self::$traceId;
    }
}
