<?php

namespace App\Tests\Service;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebInteraccion;
use App\Entity\PresupuestoWebLead;
use App\Enum\TipoPresupuestoWebInteraccion;
use App\Service\WebPresupuesto\Admin\LeadWebTimelineBuilder;
use PHPUnit\Framework\TestCase;

final class LeadWebTimelineBuilderTest extends TestCase
{
    public function testTimelineSeOrdenaPorFechaAscendente(): void
    {
        $presupuesto = new PresupuestoWeb(
            new PresupuestoWebLead('cliente@example.com', '2026-09-19'),
            'ducha',
            bin2hex(random_bytes(32)),
            ['configurador' => 'web_ducha'],
            ['lineas' => []],
            '100.00'
        );
        $items = (new LeadWebTimelineBuilder())->construir($presupuesto, [
            new PresupuestoWebInteraccion($presupuesto, null, TipoPresupuestoWebInteraccion::SOLICITA_MEDICION, ['telefono' => '600123123'], new \DateTimeImmutable('2026-09-19 12:30:00')),
            new PresupuestoWebInteraccion($presupuesto, null, TipoPresupuestoWebInteraccion::DUDA, ['mensaje' => 'Duda'], new \DateTimeImmutable('2026-09-19 10:00:00')),
        ]);

        self::assertSame('Duda sobre el presupuesto', $items[0]['titulo']);
        self::assertSame('Solicita medición', $items[1]['titulo']);
        self::assertSame('Teléfono', $items[1]['datos'][0]['label']);
    }
}
