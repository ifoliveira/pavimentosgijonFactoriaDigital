<?php

namespace App\Tests\Service;

use App\Repository\PrecioRepository;
use App\Service\PresupuestoCalculatorService;
use PHPUnit\Framework\TestCase;

class PresupuestoCalculatorServiceTest extends TestCase
{
    private PresupuestoCalculatorService $calculador;

    protected function setUp(): void
    {
        // Simulamos el repositorio sin tocar la BD
        $precios = $this->createMock(PrecioRepository::class);
        $precios->method('get')->willReturnCallback(
            fn(string $clave, string $tipo) => match($clave) {
                'albanil_base'          => 450.0,
                'fontanero_base'        => 200.0,
                'escayolista_base'      => 150.0,
                'colocador_mampara'     => 70.0,
                'albanil_minimo'        => 50.0,
                'albanil_hasta1m'       => 150.0,
                'albanil_hasta_techo'   => 350.0,
                'albanil_personalizado' => 175.0,
                'plato_pequeno'         => 420.0,
                'plato_medio'           => 480.0,
                'plato_grande'          => 590.0,
                'mampara_fija'          => 550.0,
                'mampara_fijo_corredera'=> 780.0,
                'mampara_angular'       => 880.0,
                'mampara_doble_corredera'=> 1150.0,
                'griferia_normal'       => 115.0,
                'griferia_barra'        => 295.0,
                'griferia_termostatica' => 200.0,
                'azulejo_reposicion_m2' => 32.0,
                'cola_h40'              => 80.0,
                'albanil_base'          => $tipo === 'bano_completo' ? 1800.0 : 450.0,
                'fontanero_base'        => $tipo === 'bano_completo' ? 1100.0 : 200.0,
                'electricidad_base'     => 250.0,
                'escayolista_pintura'   => 50.0,
                'albanil_hasta_techo'   => 350.0,
                'albanil_alicatado_m2'  => 0.0,
                'inodoro_convencional'  => 565.0,
                'bide_convencional'     => 165.0,
                'griferia_higienico'    => 265.0,
                'griferia_lavabo'       => 70.0,
                'mueble_80cm'           => 875.0,
                'mueble_90cm'           => 990.0,
                'mueble_100cm'          => 1150.0,
                'iluminacion_led'       => 59.0,
                'radiador_simple'       => 370.0,                
                default                 => 0.0,
            }
        );

        $this->calculador = new PresupuestoCalculatorService($precios);
    }

    // ─────────────────────────────────────────
    //  DUCHA — caso básico
    // ─────────────────────────────────────────
    public function testDuchaBasica(): void
    {
        $datos = [
            'medida_platoducha' => ['largo_cm' => 150, 'ancho_cm' => 70],
            'zona_azulejos'     => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'reposicion_azulejos' => false,
            'tipo_mampara'      => 'Fijo + corredera',
            'accion_grifo'      => false,
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        // Albañil 450 + Fontanero 200 + Alicatado 150 + Plato medio 480
        // + Cola 80 + Mampara fijo+corredera 780 + Instalación mampara 70
        $this->assertEquals(2210.0, $resultado['total']);
        $this->assertArrayHasKey('mano_obra',  $resultado);
        $this->assertArrayHasKey('materiales', $resultado);
    }

    // ─────────────────────────────────────────
    //  DUCHA — con grifería y reposición
    // ─────────────────────────────────────────
    public function testDuchaConGriferiaYReposicion(): void
    {
        $datos = [
            'medida_platoducha'   => ['largo_cm' => 150, 'ancho_cm' => 70],
            'zona_azulejos'       => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'reposicion_azulejos' => true,
            'tipo_mampara'        => 'Fijo',
            'accion_grifo'        => true,
            'tipo_griferia'       => 'barra',
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        $this->assertGreaterThan(0, $resultado['total']);
        $this->assertArrayHasKey('Grifería', $resultado['materiales']);
        $this->assertStringContainsString('m²', array_key_first(
            array_filter($resultado['materiales'], fn($k) => str_contains($k, 'm²'), ARRAY_FILTER_USE_KEY)
        ) ?: '');
    }

    // ─────────────────────────────────────────
    //  DUCHA — plato pequeño
    // ─────────────────────────────────────────
    public function testDuchaConPlatoMedidaPequena(): void
    {
        $datos = [
            'medida_platoducha'   => ['largo_cm' => 120, 'ancho_cm' => 70],
            'zona_azulejos'       => ['categoria' => 'minimo', 'altura_cm' => 30],
            'reposicion_azulejos' => false,
            'tipo_mampara'        => 'Fijo',
            'accion_grifo'        => false,
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        $this->assertArrayHasKey('Plato de ducha', $resultado['materiales']);
        $this->assertEquals(420.0, $resultado['materiales']['Plato de ducha']);
    }

    // ─────────────────────────────────────────
    //  DUCHA — escayola
    // ─────────────────────────────────────────
    public function testDuchaConEscayola(): void
    {
        $datos = [
            'medida_platoducha'   => ['largo_cm' => 150, 'ancho_cm' => 70],
            'zona_azulejos'       => ['categoria' => 'hasta_el_techo', 'altura_cm' => 240],
            'mantener_escayola'   => true,
            'reposicion_azulejos' => false,
            'tipo_mampara'        => 'Fijo',
            'accion_grifo'        => false,
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        $this->assertArrayHasKey('Escayolista', $resultado['mano_obra']);
        $this->assertEquals(150.0, $resultado['mano_obra']['Escayolista']);
    }

    // ─────────────────────────────────────────
    //  GENERAL — tipo desconocido
    // ─────────────────────────────────────────
    public function testTipoDesconocidoLanzaExcepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculador->calcular('cocina', []);
    }

    // ─────────────────────────────────────────
    //  GENERAL — min y max son ±5%
    // ─────────────────────────────────────────
    public function testMinYMaxSonCincoPorc(): void
    {
        $datos = [
            'medida_platoducha'   => ['largo_cm' => 150, 'ancho_cm' => 70],
            'zona_azulejos'       => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'reposicion_azulejos' => false,
            'tipo_mampara'        => 'Fijo',
            'accion_grifo'        => false,
        ];

        $resultado = $this->calculador->calcular('ducha', $datos);

        $this->assertEquals(
            round($resultado['total'] * 0.95, 2),
            $resultado['min']
        );
        $this->assertEquals(
            round($resultado['total'] * 1.05, 2),
            $resultado['max']
        );
    }

    // ─────────────────────────────────────────
    //  BAÑO COMPLETO — caso básico
    // ─────────────────────────────────────────
    public function testBanoCompletoBasico(): void
    {
        $datos = [
            'medida_bano'       => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'     => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'banera_o_ducha'    => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'      => 'Fijo + corredera',
            'tipo_griferia'     => 'normal',
            'bide_o_higienico'  => 'ninguno',
            'mueble_bano'       => ['quiere' => false, 'medida_cm' => null],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertGreaterThan(0, $resultado['total']);
        $this->assertArrayHasKey('mano_obra',  $resultado);
        $this->assertArrayHasKey('materiales', $resultado);
        $this->assertArrayHasKey('Albañil',    $resultado['mano_obra']);
        $this->assertArrayHasKey('Fontanero',  $resultado['mano_obra']);
        $this->assertArrayHasKey('Electricista', $resultado['mano_obra']);
    }

    // ─────────────────────────────────────────
    //  BAÑO COMPLETO — con mueble de 90cm
    // ─────────────────────────────────────────
    public function testBanoCompletoConMueble(): void
    {
        $datos = [
            'medida_bano'      => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'    => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'banera_o_ducha'   => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'     => 'Fijo',
            'tipo_griferia'    => 'normal',
            'bide_o_higienico' => 'ninguno',
            'mueble_bano'      => ['quiere' => true, 'medida_cm' => 90],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertArrayHasKey('Mueble de baño', $resultado['materiales']);
        $this->assertEquals(990.0, $resultado['materiales']['Mueble de baño']);
    }

    // ─────────────────────────────────────────
    //  BAÑO COMPLETO — con bidé
    // ─────────────────────────────────────────
    public function testBanoCompletoConBide(): void
    {
        $datos = [
            'medida_bano'      => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'    => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'banera_o_ducha'   => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'     => 'Fijo',
            'tipo_griferia'    => 'normal',
            'bide_o_higienico' => 'bide',
            'mueble_bano'      => ['quiere' => false, 'medida_cm' => null],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertArrayHasKey('Bidé', $resultado['materiales']);
        $this->assertEquals(165.0, $resultado['materiales']['Bidé']);
    }

    // ─────────────────────────────────────────
    //  BAÑO COMPLETO — con grupo higiénico
    // ─────────────────────────────────────────
    public function testBanoCompletoConHigienico(): void
    {
        $datos = [
            'medida_bano'      => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'    => ['categoria' => 'hasta_1m', 'altura_cm' => 100],
            'banera_o_ducha'   => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'     => 'Fijo',
            'tipo_griferia'    => 'normal',
            'bide_o_higienico' => 'higienico',
            'mueble_bano'      => ['quiere' => false, 'medida_cm' => null],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertArrayHasKey('Grupo higiénico', $resultado['materiales']);
        $this->assertEquals(265.0, $resultado['materiales']['Grupo higiénico']);
    }

    // ─────────────────────────────────────────
    //  BAÑO COMPLETO — alicatado hasta el techo
    // ─────────────────────────────────────────
    public function testBanoCompletoAlicatadoHastaTecho(): void
    {
        $datos = [
            'medida_bano'      => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'    => ['categoria' => 'hasta_el_techo', 'altura_cm' => 240],
            'banera_o_ducha'   => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'     => 'Fijo',
            'tipo_griferia'    => 'normal',
            'bide_o_higienico' => 'ninguno',
            'mueble_bano'      => ['quiere' => false, 'medida_cm' => null],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertArrayHasKey('Alicatado', $resultado['mano_obra']);
        $this->assertEquals(350.0, $resultado['mano_obra']['Alicatado']);
    }

    // ─────────────────────────────────────────
    //  BAÑO COMPLETO — con escayola
    // ─────────────────────────────────────────
    public function testBanoCompletoConEscayola(): void
    {
        $datos = [
            'medida_bano'      => ['largo_cm' => 200, 'ancho_cm' => 150, 'alto_cm' => 240],
            'zona_azulejos'    => ['categoria' => 'hasta_el_techo', 'altura_cm' => 240],
            'mantener_escayola'=> true,
            'banera_o_ducha'   => ['tipo' => 'ducha', 'largo_cm' => 150, 'ancho_cm' => 70],
            'tipo_mampara'     => 'Fijo',
            'tipo_griferia'    => 'normal',
            'bide_o_higienico' => 'ninguno',
            'mueble_bano'      => ['quiere' => false, 'medida_cm' => null],
        ];

        $resultado = $this->calculador->calcular('baño_completo', $datos);

        $this->assertArrayHasKey('Escayolista', $resultado['mano_obra']);
    }    
}