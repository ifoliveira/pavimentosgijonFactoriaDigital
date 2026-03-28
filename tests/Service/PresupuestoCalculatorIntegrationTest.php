<?php

namespace App\Tests\Service;

use App\Service\PresupuestoCalculatorService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PresupuestoCalculatorIntegrationTest extends KernelTestCase
{
    private PresupuestoCalculatorService $calculador;

    protected function setUp(): void
    {
        // Arranca el kernel de Symfony en entorno test
        self::bootKernel();
        $this->calculador = static::getContainer()->get(PresupuestoCalculatorService::class);
    }

    // ─────────────────────────────────────────
    //  DUCHA — verifica que el total cuadra
    //  con los precios reales de la BD
    // ─────────────────────────────────────────
    public function testDuchaBasicaConPreciosReales(): void
    {
        $datos = [
            'medida_platoducha'   => ['largo_cm' => 150, 'ancho_cm' => 70],
            'zona_azulejos'       => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'reposicion_azulejos' => false,
            'tipo_mampara'        => 'Fijo + corredera',
            'accion_grifo'        => false,
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        // Albañil 450 + Fontanero 200 + Alicatado 150
        // + Plato medio 480 + Cola 80 + Mampara 780 + Instalación 70
        $this->assertEquals(2210.0, $resultado['total']);
        $this->assertEquals(round(2210 * 0.95, 2), $resultado['min']);
        $this->assertEquals(round(2210 * 1.05, 2), $resultado['max']);
    }

    public function testBanoCompletoConPreciosReales(): void
    {
        $datos = [
            'medida_bano'      => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'    => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'banera_o_ducha'   => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'     => 'Fijo + corredera',
            'tipo_griferia'    => 'normal',
            'bide_o_higienico' => 'ninguno',
            'mueble_bano'      => ['quiere' => false, 'medida_cm' => null],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertGreaterThan(0, $resultado['total']);
        $this->assertArrayHasKey('Albañil',      $resultado['mano_obra']);
        $this->assertArrayHasKey('Plato de ducha', $resultado['materiales']);

        // Verificamos que el total coincide con la suma de líneas
        $sumaLineas = array_sum($resultado['mano_obra']) + array_sum($resultado['materiales']);
        $this->assertEquals(round($sumaLineas, 2), $resultado['total']);
    }

    // ─────────────────────────────────────────
    //  Verifica que un precio cambiado en BD
    //  se refleja en el resultado
    // ─────────────────────────────────────────
    public function testCambioDePrecioSeRefleja(): void
    {
        $datos = [
            'medida_platoducha'   => ['largo_cm' => 150, 'ancho_cm' => 70],
            'zona_azulejos'       => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'reposicion_azulejos' => false,
            'tipo_mampara'        => 'Fijo',
            'accion_grifo'        => false,
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        // El total debe ser la suma exacta de lo que hay en BD
        $sumaLineas = array_sum($resultado['mano_obra']) + array_sum($resultado['materiales']);
        $this->assertEquals(round($sumaLineas, 2), $resultado['total']);
    }
}
