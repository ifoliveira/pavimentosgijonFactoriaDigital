<?php

namespace App\Tests\Service;

use App\Service\Tesoreria\BolsaGastosCalculator;
use PHPUnit\Framework\TestCase;

class BolsaGastosCalculatorTest extends TestCase
{
    public function testUnaFacturaSustituyeLaPrevisionSinNecesitarAsignacion(): void
    {
        $resultado = $this->calcular([
            $this->evento('p1', 'presupuesto', '2027-01-01', 1000000),
            $this->evento('f1', 'factura', '2027-01-02', 300000),
        ]);

        self::assertSame(700000, $resultado['saldoCentimos']);
        self::assertSame(1000000, $resultado['aportacionesCentimos']);
        self::assertSame(300000, $resultado['consumidoCentimos']);
        self::assertSame(0, $resultado['excesoFacturasCentimos']);
        self::assertSame(-300000, $resultado['movimientos'][1]['aplicadoCentimos']);
        self::assertSame(700000, $resultado['pendientes'][0]['restanteCentimos']);
    }

    public function testElPagoYLaAsignacionPosterioresNoCambianElConsumo(): void
    {
        $presupuesto = $this->evento('p1', 'presupuesto', '2027-01-01', 1000000);
        $factura = $this->evento('f1', 'factura', '2027-01-02', 300000);
        $antes = $this->calcular([$presupuesto, $factura]);
        $factura['pagada'] = true;
        $factura['proyectoId'] = 18;
        $despues = $this->calcular([$presupuesto, $factura]);

        self::assertSame($antes['saldoCentimos'], $despues['saldoCentimos']);
        self::assertSame($antes['consumidoCentimos'], $despues['consumidoCentimos']);
        self::assertSame($antes['pendientes'], $despues['pendientes']);
    }

    public function testUnaFacturaSinReservaNoConsumePresupuestosPosteriores(): void
    {
        $resultado = $this->calcular([
            $this->evento('f1', 'factura', '2027-01-01', 300000),
            $this->evento('p1', 'presupuesto', '2027-01-02', 1000000),
        ]);

        self::assertSame(1000000, $resultado['saldoCentimos']);
        self::assertSame(0, $resultado['consumidoCentimos']);
        self::assertSame(300000, $resultado['excesoFacturasCentimos']);
        self::assertSame(0, $resultado['movimientos'][0]['saldoCentimos']);
        self::assertNotNull($resultado['movimientos'][0]['incidencia']);
    }

    public function testUnExcesoParcialNoSeArrastraComoDeudaDeLaBolsa(): void
    {
        $resultado = $this->calcular([
            $this->evento('p1', 'presupuesto', '2027-01-01', 10000),
            $this->evento('f1', 'factura', '2027-01-02', 15000),
            $this->evento('p2', 'presupuesto', '2027-01-03', 20000),
            $this->evento('f2', 'factura', '2027-01-04', 3000),
        ]);

        self::assertSame(17000, $resultado['saldoCentimos']);
        self::assertSame(13000, $resultado['consumidoCentimos']);
        self::assertSame(5000, $resultado['excesoFacturasCentimos']);
        self::assertSame(5000, $resultado['movimientos'][1]['excesoCentimos']);
    }

    public function testLosIdentificadoresDuplicadosNoRepitenAportacionesNiConsumos(): void
    {
        $presupuesto = $this->evento('p1', 'presupuesto', '2027-01-01', 10000);
        $factura = $this->evento('f1', 'factura', '2027-01-02', 3000);
        $resultado = $this->calcular([$factura, $presupuesto, $factura, $presupuesto]);

        self::assertCount(2, $resultado['movimientos']);
        self::assertSame(10000, $resultado['aportacionesCentimos']);
        self::assertSame(3000, $resultado['consumidoCentimos']);
        self::assertSame(7000, $resultado['saldoCentimos']);
    }

    public function testLosAbonosSeAdviertenSinRestaurarPrevisionAutomaticamente(): void
    {
        $resultado = $this->calcular([
            $this->evento('p1', 'presupuesto', '2027-01-01', 10000),
            $this->evento('f1', 'factura', '2027-01-02', 3000),
            $this->evento('f2', 'factura', '2027-01-03', -3000),
        ]);

        self::assertSame(7000, $resultado['saldoCentimos']);
        self::assertSame(3000, $resultado['consumidoCentimos']);
        self::assertSame(0, $resultado['movimientos'][2]['aplicadoCentimos']);
        self::assertSame('Abono pendiente de ajuste', $resultado['movimientos'][2]['incidencia']);
    }

    public function testLosAjustesAumentanOReducenReservaSinContarComoFacturas(): void
    {
        $resultado = $this->calcular([
            $this->evento('a1', 'ajuste', '2027-01-01', 10000),
            $this->evento('a2', 'ajuste', '2027-01-02', -2000),
            $this->evento('f1', 'factura', '2027-01-03', 3000),
        ]);

        self::assertSame(0, $resultado['aportacionesCentimos']);
        self::assertSame(8000, $resultado['ajustesCentimos']);
        self::assertSame(3000, $resultado['consumidoCentimos']);
        self::assertSame(5000, $resultado['saldoCentimos']);
        self::assertSame('a1', $resultado['pendientes'][0]['id']);
    }

    public function testUnaReduccionDePresupuestoSeSeparaDeLosAjustesYFacturas(): void
    {
        $resultado = $this->calcular([
            $this->evento('p1', 'presupuesto', '2027-01-01', 10000),
            $this->evento('p2', 'presupuesto', '2027-01-02', -2000),
            $this->evento('f1', 'factura', '2027-01-03', 3000),
        ]);

        self::assertSame(8000, $resultado['aportacionesCentimos']);
        self::assertSame(0, $resultado['ajustesCentimos']);
        self::assertSame(3000, $resultado['consumidoCentimos']);
        self::assertSame(5000, $resultado['saldoCentimos']);
    }

    /** @dataProvider tiposReduccion */
    public function testLasReduccionesNoGeneranSaldoNegativoNiConsumoFuturo(string $tipo): void
    {
        $resultado = $this->calcular([
            $this->evento('p1', 'presupuesto', '2027-01-01', 10000),
            $this->evento('r1', $tipo, '2027-01-02', -15000),
            $this->evento('p2', 'presupuesto', '2027-01-03', 20000),
        ]);

        self::assertSame(20000, $resultado['saldoCentimos']);
        self::assertSame(-10000, $resultado['movimientos'][1]['aplicadoCentimos']);
        self::assertSame(5000, $resultado['movimientos'][1]['excesoCentimos']);
        self::assertNotNull($resultado['movimientos'][1]['incidencia']);
        self::assertSame(0, $resultado['excesoFacturasCentimos']);
        self::assertSame(0, $resultado['consumidoCentimos']);
    }

    public static function tiposReduccion(): iterable
    {
        yield 'ajuste' => ['ajuste'];
        yield 'presupuesto complementario' => ['presupuesto'];
    }

    public function testConsumePrimeroLaAportacionMasAntiguaYConservaSuOrigen(): void
    {
        $primero = $this->evento('p1', 'presupuesto', '2027-01-01', 10000);
        $primero['fechaPrevista'] = new \DateTimeImmutable('2027-06-01');
        $segundo = $this->evento('p2', 'presupuesto', '2027-01-02', 20000);
        $segundo['documentoId'] = 42;
        $segundo['proyectoId'] = 17;
        $resultado = $this->calcular([
            $primero,
            $segundo,
            $this->evento('f1', 'factura', '2027-01-03', 15000),
        ]);

        self::assertCount(1, $resultado['pendientes']);
        self::assertSame('p2', $resultado['pendientes'][0]['id']);
        self::assertSame(20000, $resultado['pendientes'][0]['importeCentimos']);
        self::assertSame(15000, $resultado['pendientes'][0]['restanteCentimos']);
        self::assertSame(42, $resultado['pendientes'][0]['documentoId']);
        self::assertSame(17, $resultado['pendientes'][0]['proyectoId']);
        self::assertEquals($segundo['fechaPrevista'], $resultado['pendientes'][0]['fechaPrevista']);
    }

    public function testElResultadoEsReproduciblePorDiaTipoEIdentificador(): void
    {
        $eventos = [
            $this->evento('f2', 'factura', '2027-01-01 09:00:00', 7000),
            $this->evento('a1', 'ajuste', '2027-01-01 10:00:00', 5000),
            $this->evento('p1', 'presupuesto', '2027-01-01 15:00:00', 10000),
            $this->evento('f1', 'factura', '2027-01-01 12:00:00', 11000),
        ];
        $resultado = $this->calcular($eventos);

        self::assertSame($resultado, $this->calcular(array_reverse($eventos)));
        self::assertSame(['p1', 'f1', 'f2', 'a1'], array_column($resultado['movimientos'], 'id'));
        self::assertSame(5000, $resultado['saldoCentimos']);
        self::assertSame(8000, $resultado['excesoFacturasCentimos']);
        self::assertSame(7000, $resultado['movimientos'][2]['excesoCentimos']);
        self::assertSame('a1', $resultado['pendientes'][0]['id']);
    }

    public function testElCalculoNoIntroduceUnaFechaDeCorteNiRedondeos(): void
    {
        $resultado = $this->calcular([
            $this->evento('p1', 'presupuesto', '2020-12-31', 10001),
            $this->evento('f1', 'factura', '2030-01-01', 9999),
        ]);

        self::assertSame(2, $resultado['saldoCentimos']);
        self::assertSame(2, $resultado['pendientes'][0]['restanteCentimos']);
        self::assertCount(2, $resultado['movimientos']);
    }

    public function testSinEventosDevuelveUnaBolsaVacia(): void
    {
        $resultado = $this->calcular([]);

        self::assertSame(0, $resultado['saldoCentimos']);
        self::assertSame([], $resultado['movimientos']);
        self::assertSame([], $resultado['pendientes']);
    }

    private function calcular(array $eventos): array
    {
        $resultado = (new BolsaGastosCalculator())->calcular($eventos);

        self::assertSame(
            $resultado['aportacionesCentimos'] + $resultado['ajustesCentimos'] - $resultado['consumidoCentimos'],
            $resultado['saldoCentimos'],
        );
        self::assertSame(
            array_sum(array_column($resultado['pendientes'], 'restanteCentimos')),
            $resultado['saldoCentimos'],
        );
        foreach ($resultado['movimientos'] as $movimiento) {
            self::assertGreaterThanOrEqual(0, $movimiento['saldoCentimos']);
        }

        return $resultado;
    }

    private function evento(string $id, string $tipo, string $fecha, int $importe): array
    {
        return [
            'id' => $id,
            'tipo' => $tipo,
            'fecha' => new \DateTimeImmutable($fecha),
            'importeCentimos' => $importe,
            'fechaPrevista' => new \DateTimeImmutable('2027-03-01'),
            'concepto' => $id,
        ];
    }
}
