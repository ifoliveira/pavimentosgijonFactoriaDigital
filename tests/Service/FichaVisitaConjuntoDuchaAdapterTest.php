<?php

namespace App\Tests\Service;

use App\Service\FichaVisita\FichaVisitaConjuntoDuchaAdapter;
use PHPUnit\Framework\TestCase;

final class FichaVisitaConjuntoDuchaAdapterTest extends TestCase
{
    private FichaVisitaConjuntoDuchaAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new FichaVisitaConjuntoDuchaAdapter();
    }

    public function testMapeaCenefaSinInventarAltura(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_cenefa' => true],
                'mampara' => ['entre_paredes' => true, 'corredera' => true],
                'griferia' => ['monomando' => true],
                'revestimiento' => ['dos_paredes' => true],
            ],
            'tecnicos' => [
                'plato' => ['largo_cm' => 170, 'ancho_cm' => 70],
                'revestimiento' => ['largo_cm' => 170, 'ancho_cm' => 70, 'altura_cm' => 60],
            ],
        ], $this->configurador());

        self::assertSame('banera_plato_cenefa', $result['valores']['selector_mano_obra']['tipo_trabajo']);
        self::assertSame(2, $result['valores']['revestimientos']['metros_azulejo']);
    }

    public function testRetirarPlatoPriorizaPlatoPorPlato(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => ['plato_retirada' => ['retirar_plato' => true, 'hasta_techo' => true]],
            'tecnicos' => ['plato' => ['largo_cm' => 120, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertSame('plato_por_plato', $result['valores']['selector_mano_obra']['tipo_trabajo']);
    }

    public function testAperturaCentralYCorrederaCoexisten(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['entre_paredes' => true, 'corredera' => true, 'apertura_central' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 160, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertSame('dos_fijos_dos_correderas', $result['valores']['selector_mampara']['tipo_apertura']);
        self::assertSame([], $result['avisos']);
    }

    public function testSinMamparaYConservarGrifoExcluyenComponentes(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['sin_mampara' => true],
                'griferia' => ['conservar_grifo' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 160, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertContains('selector_mampara', $result['componentes_excluidos']);
        self::assertContains('griferia', $result['componentes_excluidos']);
        self::assertArrayNotHasKey('selector_mampara', $result['valores']);
        self::assertArrayNotHasKey('griferia', $result['valores']);
    }

    public function testSinRociadorSeConservaComoSinCorrespondencia(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'griferia' => ['termostatica' => true, 'sin_rociador' => true],
            ],
        ], $this->configurador());

        self::assertSame('termostatica', $result['valores']['griferia']['tipo']);
        self::assertNotEmpty($result['sin_correspondencia']);
    }

    public function testGriferiaMonomandoMarcadaRellenaUsoYTipo(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'griferia' => ['monomando' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertSame('ducha', $result['valores']['griferia']['uso']);
        self::assertSame('monomando', $result['valores']['griferia']['tipo']);
        self::assertNotContains('griferia.tipo', $result['pendientes']);
    }

    public function testGriferiaMonomandoEnDatosTecnicosTambienSeMapea(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'griferia' => [],
            ],
            'tecnicos' => [
                'plato' => ['largo_cm' => 170, 'ancho_cm' => 70],
                'griferia' => ['tipo' => 'Monomando', 'acabado' => 'cromo'],
            ],
        ], $this->configurador());

        self::assertSame('ducha', $result['valores']['griferia']['uso']);
        self::assertSame('monomando', $result['valores']['griferia']['tipo']);
        self::assertNotContains('griferia.tipo', $result['pendientes']);
    }

    public function testGriferiaMonomandoNoSeExcluyeAunqueConservarGrifoVengaMarcado(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'griferia' => ['conservar_grifo' => true, 'monomando' => true],
            ],
            'tecnicos' => [
                'plato' => ['largo_cm' => 170, 'ancho_cm' => 70],
                'griferia' => ['tipo' => 'monomando'],
            ],
        ], $this->configurador());

        self::assertNotContains('griferia', $result['componentes_excluidos']);
        self::assertSame('ducha', $result['valores']['griferia']['uso']);
        self::assertSame('monomando', $result['valores']['griferia']['tipo']);
        self::assertNotEmpty($result['avisos']);
    }

    public function testReparaAdaptacionAnteriorConGriferiaExcluidaPeroMonomandoDetectado(): void
    {
        $adaptacion = [
            'valores' => ['selector_plato_ducha' => ['largo' => 175, 'ancho' => 80]],
            'avisos' => [],
            'componentes_excluidos' => ['griferia'],
            'mapeo' => [
                ['origen' => 'griferia.conservar_grifo', 'destino' => 'griferia', 'valor' => 'excluido'],
            ],
        ];

        $reparada = $this->adapter->completarGriferiaDesdeDatos([
            'casillas' => ['griferia' => ['conservar_grifo' => true, 'monomando' => true]],
            'tecnicos' => ['griferia' => ['tipo' => 'monomando']],
        ], $adaptacion);

        self::assertNotContains('griferia', $reparada['componentes_excluidos']);
        self::assertSame('ducha', $reparada['valores']['griferia']['uso']);
        self::assertSame('monomando', $reparada['valores']['griferia']['tipo']);
        self::assertSame('griferia.tipo', $reparada['mapeo'][0]['destino']);
    }

    public function testPlato170Por70EntreParedesDerivaFrente170(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['entre_paredes' => true, 'corredera' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertSame(170, $result['valores']['selector_mampara']['ancho_frente']);
        self::assertArrayNotHasKey('ancho_lateral', $result['valores']['selector_mampara']);
        self::assertSame('selector_plato_ducha.largo', $result['procedencia']['selector_mampara.ancho_frente']['origen']);
    }

    public function testPlato170Por70ConLateralDerivaFrenteYLateral(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['con_lateral' => true, 'corredera' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertSame(170, $result['valores']['selector_mampara']['ancho_frente']);
        self::assertSame(70, $result['valores']['selector_mampara']['ancho_lateral']);
        self::assertSame('selector_plato_ducha.ancho', $result['procedencia']['selector_mampara.ancho_lateral']['origen']);
    }

    public function testCristalFijoSinMedidaRequiereRevision(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['cristal_fijo' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        self::assertSame('fijo', $result['valores']['selector_mampara']['tipo_instalacion']);
        self::assertContains('selector_mampara.ancho_frente', $result['pendientes']);
        self::assertArrayNotHasKey('ancho_frente', $result['valores']['selector_mampara']);
    }

    public function testCristalFijoConMedidaInformadaUsaEsaMedida(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['cristal_fijo' => true],
            ],
            'tecnicos' => [
                'plato' => ['largo_cm' => 170, 'ancho_cm' => 70],
                'mampara' => ['medida_cm' => 95],
            ],
        ], $this->configurador());

        self::assertSame(95, $result['valores']['selector_mampara']['ancho_frente']);
        self::assertSame('usuario', $result['procedencia']['selector_mampara.ancho_frente']['tipo']);
    }

    public function testMedidaCorregidaPorUsuarioPrevaleceSobreDerivacion(): void
    {
        $adaptacion = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['entre_paredes' => true, 'corredera' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        $revisada = $this->adapter->reconciliarRevision([
            'selector_plato_ducha' => ['largo' => 170, 'ancho' => 70],
            'selector_mampara' => ['tipo_instalacion' => 'frente', 'ancho_frente' => 165],
        ], $adaptacion);

        self::assertSame(165, $revisada['valores']['selector_mampara']['ancho_frente']);
        self::assertSame('usuario', $revisada['procedencia']['selector_mampara.ancho_frente']['tipo']);
    }

    public function testCambioDePlatoActualizaDerivadasYConservaExplicitas(): void
    {
        $adaptacion = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['con_lateral' => true, 'corredera' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        $adaptacion['procedencia']['selector_mampara.ancho_lateral'] = [
            'tipo' => 'usuario',
            'origen' => 'revision',
            'mensaje' => 'Informada expresamente por el usuario',
        ];

        $revisada = $this->adapter->reconciliarRevision([
            'selector_plato_ducha' => ['largo' => 180, 'ancho' => 80],
            'selector_mampara' => [
                'tipo_instalacion' => 'frente_con_lateral_fijo',
                'ancho_frente' => 170,
                'ancho_lateral' => 72,
            ],
        ], $adaptacion);

        self::assertSame(180, $revisada['valores']['selector_mampara']['ancho_frente']);
        self::assertSame(72, $revisada['valores']['selector_mampara']['ancho_lateral']);
    }

    public function testCambioDeTipologiaNoEnviaMedidasQueNoCorrespondan(): void
    {
        $adaptacion = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['con_lateral' => true, 'corredera' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170, 'ancho_cm' => 70]],
        ], $this->configurador());

        $revisada = $this->adapter->reconciliarRevision([
            'selector_plato_ducha' => ['largo' => 170, 'ancho' => 70],
            'selector_mampara' => [
                'tipo_instalacion' => 'frente',
                'ancho_frente' => 170,
                'ancho_lateral' => 70,
            ],
        ], $adaptacion);

        self::assertArrayNotHasKey('ancho_lateral', $revisada['valores']['selector_mampara']);
    }

    public function testMedidaNecesariaDelPlatoAusenteRequiereRevision(): void
    {
        $result = $this->adapter->adaptar([
            'casillas' => [
                'plato_retirada' => ['hasta_techo' => true],
                'mampara' => ['con_lateral' => true, 'corredera' => true],
            ],
            'tecnicos' => ['plato' => ['largo_cm' => 170]],
        ], $this->configurador());

        self::assertContains('selector_plato_ducha.ancho', $result['pendientes']);
        self::assertArrayNotHasKey('ancho_lateral', $result['valores']['selector_mampara']);
    }

    private function configurador(): array
    {
        return [
            'codigo' => 'conjunto_ducha',
            'tipo' => 'compuesto',
            'componentes' => [
                ['codigo' => 'griferia', 'configurador' => ['campos' => [
                    ['codigo' => 'uso', 'tipo' => 'seleccion', 'opciones' => [['codigo' => 'ducha', 'etiqueta' => 'Ducha']]],
                    ['codigo' => 'tipo', 'tipo' => 'seleccion', 'opciones' => [
                        ['codigo' => 'monomando', 'etiqueta' => 'Monomando'],
                        ['codigo' => 'termostatica', 'etiqueta' => 'Termostática'],
                        ['codigo' => 'empotrado', 'etiqueta' => 'Empotrado'],
                    ]],
                    ['codigo' => 'descripcion', 'tipo' => 'texto'],
                ]]],
                ['codigo' => 'selector_plato_ducha', 'configurador' => ['campos' => [
                    ['codigo' => 'largo', 'tipo' => 'entero'],
                    ['codigo' => 'ancho', 'tipo' => 'entero'],
                    ['codigo' => 'color', 'tipo' => 'seleccion', 'opciones' => [['codigo' => 'blanco', 'etiqueta' => 'Blanco']]],
                ]]],
                ['codigo' => 'selector_mampara', 'configurador' => ['campos' => [
                    ['codigo' => 'tipo_instalacion', 'tipo' => 'seleccion', 'opciones' => [
                        ['codigo' => 'frente', 'etiqueta' => 'Solo frente'],
                        ['codigo' => 'frente_con_lateral_fijo', 'etiqueta' => 'Frente + lateral fijo'],
                        ['codigo' => 'fijo', 'etiqueta' => 'Fijo'],
                    ]],
                    ['codigo' => 'ancho_frente', 'tipo' => 'decimal'],
                    ['codigo' => 'ancho_lateral', 'tipo' => 'decimal'],
                    ['codigo' => 'tipo_apertura', 'tipo' => 'seleccion', 'opciones' => [
                        ['codigo' => 'corredera', 'etiqueta' => 'Corredera'],
                        ['codigo' => 'abatible', 'etiqueta' => 'Abatible'],
                        ['codigo' => 'plegable', 'etiqueta' => 'Plegable'],
                        ['codigo' => 'dos_fijos_dos_correderas', 'etiqueta' => 'Dos fijos y dos correderas'],
                    ]],
                ]]],
                ['codigo' => 'selector_mano_obra', 'configurador' => ['campos' => [
                    ['codigo' => 'tipo_trabajo', 'tipo' => 'seleccion', 'opciones' => [
                        ['codigo' => 'plato_por_plato', 'etiqueta' => 'Plato por plato'],
                        ['codigo' => 'banera_plato_cenefa', 'etiqueta' => 'Plato por bañera hasta cenefa'],
                        ['codigo' => 'banera_plato_zona_ducha', 'etiqueta' => 'Plato por bañera hasta techo'],
                    ]],
                ]]],
                ['codigo' => 'revestimientos', 'configurador' => ['campos' => [
                    ['codigo' => 'azulejo', 'tipo' => 'booleano'],
                    ['codigo' => 'metros_azulejo', 'tipo' => 'entero'],
                    ['codigo' => 'pavimento', 'tipo' => 'booleano'],
                ]]],
            ],
        ];
    }
}
