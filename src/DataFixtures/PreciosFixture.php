<?php

namespace App\DataFixtures;

use App\Entity\Precio;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PreciosFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
$precios = [
    // ── DUCHA ──────────────────────────────────────────────
    ['clave' => 'albanil_base',         'valor' => 450,  'descripcion' => 'Albañil — Retirada de plato, preparar paredes y colocar plato de ducha', 'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'fontanero_base',       'valor' => 200,  'descripcion' => 'Fontanero — Instalación tomas, plato de ducha, desagües y grifo',         'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'escayolista_base',     'valor' => 150,  'descripcion' => 'Escayolista — Reparación de techo y moldura de escayola',                 'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'colocador_mampara',    'valor' => 70,   'descripcion' => 'Instalación de mampara',                                                  'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'albanil_minimo',       'valor' => 50,   'descripcion' => 'Albañil — Alicatado mínimo (zona pequeña)',                               'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'albanil_hasta1m',      'valor' => 150,  'descripcion' => 'Albañil — Alicatado hasta 1 metro',                                       'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'albanil_hasta_techo',  'valor' => 350,  'descripcion' => 'Albañil — Alicatado hasta el techo',                                      'grupo' => 'mano_obra',  'tipo' => 'ducha'],
    ['clave' => 'albanil_personalizado','valor' => 175,  'descripcion' => 'Albañil — Alicatado por m²',                                              'grupo' => 'mano_obra',  'tipo' => 'ducha'],

    // ── BAÑO COMPLETO ───────────────────────────────────────
    ['clave' => 'albanil_base',         'valor' => 1800, 'descripcion' => 'Albañil — Derribo, azulejos, pavimento, materiales y desescombro',        'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'fontanero_base',       'valor' => 1100, 'descripcion' => 'Fontanero — Tomas de agua, grifería, desagües, sifones e inodoro',        'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'electricidad_base',    'valor' => 250,  'descripcion' => 'Electricista — Llave, enchufe e iluminación de espejo y techo',           'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'escayolista_pintura',  'valor' => 50,   'descripcion' => 'Pintura de techo',                                                        'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'escayolista_base',     'valor' => 200,  'descripcion' => 'Escayolista — Reparación de techo y moldura de escayola',                 'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'colocador_mampara',    'valor' => 100,  'descripcion' => 'Instalación de mampara',                                                  'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'albanil_hasta1m',      'valor' => 150,  'descripcion' => 'Albañil — Alicatado hasta 1 metro',                                       'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'albanil_hasta_techo',  'valor' => 350,  'descripcion' => 'Albañil — Alicatado hasta el techo',                                      'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],
    ['clave' => 'albanil_alicatado_m2', 'valor' => 0,    'descripcion' => 'Albañil — Alicatado por m² (pendiente definir precio)',                   'grupo' => 'mano_obra',  'tipo' => 'bano_completo'],

    // ── COMPARTIDOS (todos) ─────────────────────────────────
    ['clave' => 'plato_pequeno',           'valor' => 420,  'descripcion' => 'Plato de ducha tamaño pequeño',              'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'plato_medio',             'valor' => 480,  'descripcion' => 'Plato de ducha tamaño medio',                'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'plato_grande',            'valor' => 590,  'descripcion' => 'Plato de ducha tamaño grande',               'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mampara_fija',            'valor' => 550,  'descripcion' => 'Mampara fija',                               'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mampara_fijo_corredera',  'valor' => 780,  'descripcion' => 'Mampara fijo + corredera',                   'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mampara_angular',         'valor' => 880,  'descripcion' => 'Mampara angular',                            'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mampara_doble_corredera', 'valor' => 1150, 'descripcion' => 'Mampara doble corredera',                    'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'griferia_normal',         'valor' => 115,  'descripcion' => 'Grifería monomando estándar',                'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'griferia_barra',          'valor' => 295,  'descripcion' => 'Columna/barra de ducha',                     'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'griferia_termostatica',   'valor' => 200,  'descripcion' => 'Grifería termostática',                      'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'griferia_lavabo',         'valor' => 70,   'descripcion' => 'Grifería lavabo monomando',                  'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'griferia_bide',           'valor' => 70,   'descripcion' => 'Grifería bidé monomando',                    'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'griferia_higienico',      'valor' => 265,  'descripcion' => 'Grupo higiénico',                            'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'inodoro_convencional',    'valor' => 565,  'descripcion' => 'Inodoro ROCA con tapa amortiguada',          'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'inodoro_suspendido',      'valor' => 650,  'descripcion' => 'Inodoro suspendido',                         'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'bide_convencional',       'valor' => 165,  'descripcion' => 'Bidé ROCA con tapa amortiguada',             'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'bide_suspendido',         'valor' => 250,  'descripcion' => 'Bidé suspendido',                            'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mueble_80cm',             'valor' => 875,  'descripcion' => 'Mueble de baño 80cm con lavabo y espejo',    'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mueble_90cm',             'valor' => 990,  'descripcion' => 'Mueble de baño 90cm con lavabo y espejo',    'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'mueble_100cm',            'valor' => 1150, 'descripcion' => 'Mueble de baño 100cm con lavabo y espejo',   'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'azulejo_reposicion_m2',   'valor' => 32,   'descripcion' => 'Reposición de azulejo por m²',               'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'cola_h40',                'valor' => 80,   'descripcion' => 'Cola y materiales de agarre H40',            'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'iluminacion_led',         'valor' => 59,   'descripcion' => 'Foco de techo LED para baño',                'grupo' => 'materiales', 'tipo' => 'todos'],
    ['clave' => 'radiador_simple',         'valor' => 370,  'descripcion' => 'Radiador toallero simple',                   'grupo' => 'materiales', 'tipo' => 'todos'],
];

foreach ($precios as $datos) {
    $precio = new Precio();
    $precio->setClave($datos['clave'])
           ->setValor($datos['valor'])
           ->setDescripcion($datos['descripcion'])
           ->setGrupo($datos['grupo'])
           ->setTipoReforma($datos['tipo']);
    $manager->persist($precio);
}

$manager->flush();
    }
}