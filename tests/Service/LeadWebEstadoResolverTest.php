<?php

namespace App\Tests\Service;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebInteraccion;
use App\Entity\PresupuestoWebLead;
use App\Enum\TipoPresupuestoWebInteraccion;
use App\Service\WebPresupuesto\Admin\LeadWebEstadoResolver;
use PHPUnit\Framework\TestCase;

final class LeadWebEstadoResolverTest extends TestCase
{
    /**
     * @dataProvider estadosPorInteraccion
     */
    public function testEstadosPorInteraccion(TipoPresupuestoWebInteraccion $tipo, string $estado, ?string $accion): void
    {
        $presupuesto = $this->presupuesto();
        $decision = (new LeadWebEstadoResolver())->resolver($presupuesto, [
            new PresupuestoWebInteraccion($presupuesto, null, $tipo, [], new \DateTimeImmutable('2026-09-19 12:00:00')),
        ]);

        self::assertSame($estado, $decision->estado);
        self::assertSame($accion, $decision->siguienteAccion);
    }

    public function testPresupuestoVistoSinInteraccion(): void
    {
        $presupuesto = $this->presupuesto();
        $presupuesto->registrarVisualizacion(new \DateTimeImmutable('2026-09-19 12:00:00'));

        $decision = (new LeadWebEstadoResolver())->resolver($presupuesto, []);

        self::assertSame('PRESUPUESTO_VISTO', $decision->estado);
        self::assertSame('SEGUIMIENTO_AUTOMATICO', $decision->siguienteAccion);
    }

    public function testPresupuestoEnviadoSinVisualizacionesNiInteracciones(): void
    {
        $decision = (new LeadWebEstadoResolver())->resolver($this->presupuesto(), []);

        self::assertSame('PRESUPUESTO_ENVIADO', $decision->estado);
        self::assertSame('SEGUIMIENTO_AUTOMATICO', $decision->siguienteAccion);
    }

    public function testUsaLaInteraccionMasReciente(): void
    {
        $presupuesto = $this->presupuesto();
        $decision = (new LeadWebEstadoResolver())->resolver($presupuesto, [
            new PresupuestoWebInteraccion($presupuesto, null, TipoPresupuestoWebInteraccion::DUDA, [], new \DateTimeImmutable('2026-09-19 12:00:00')),
            new PresupuestoWebInteraccion($presupuesto, null, TipoPresupuestoWebInteraccion::SOLICITA_MEDICION, [], new \DateTimeImmutable('2026-09-19 12:10:00')),
        ]);

        self::assertSame('VISITA_SOLICITADA', $decision->estado);
        self::assertSame('CONFIRMAR_VISITA', $decision->siguienteAccion);
    }

    public function estadosPorInteraccion(): iterable
    {
        yield 'duda' => [TipoPresupuestoWebInteraccion::DUDA, 'DUDA_PENDIENTE', 'RESPONDER_DUDA'];
        yield 'cambio' => [TipoPresupuestoWebInteraccion::CAMBIO, 'CAMBIO_SOLICITADO', 'REVISAR_CAMBIO'];
        yield 'contacto' => [TipoPresupuestoWebInteraccion::SOLICITA_CONTACTO, 'CONTACTO_SOLICITADO', 'LLAMAR'];
        yield 'medicion' => [TipoPresupuestoWebInteraccion::SOLICITA_MEDICION, 'VISITA_SOLICITADA', 'CONFIRMAR_VISITA'];
        yield 'cerrado' => [TipoPresupuestoWebInteraccion::NO_CONTINUA, 'CERRADO', null];
    }

    private function presupuesto(): PresupuestoWeb
    {
        return new PresupuestoWeb(
            new PresupuestoWebLead('cliente@example.com', '2026-09-19'),
            'ducha',
            bin2hex(random_bytes(32)),
            ['configurador' => 'web_ducha'],
            ['lineas' => []],
            '100.00'
        );
    }
}
