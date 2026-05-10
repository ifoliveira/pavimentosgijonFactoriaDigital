<?php

namespace App\DataFixtures;

use App\Entity\PresupuestoConfigurador;
use App\Entity\PresupuestoConfiguradorCampo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PresupuestoConfiguradorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $ducha = new PresupuestoConfigurador();
        $ducha->setCodigo('ducha');
        $ducha->setNombre('Cambio bañera por ducha');
        $ducha->setActivo(true);
        $ducha->setOrden(1);

        $manager->persist($ducha);

        $this->crearCampo($manager, $ducha, 'largo_plato', 'Largo del plato', 'number', true, 10);
        $this->crearCampo($manager, $ducha, 'ancho_plato', 'Ancho del plato', 'number', true, 20);

        $this->crearCampo($manager, $ducha, 'entre_paredes', '¿Está entre dos paredes?', 'boolean', false, 30);

        $this->crearCampo($manager, $ducha, 'alicatado', 'Alicatado', 'select', true, 40, [
            ['value' => 'minimo', 'label' => 'Solo lo mínimo'],
            ['value' => 'hasta_1m', 'label' => 'Hasta 1 metro'],
            ['value' => 'hasta_techo', 'label' => 'Hasta el techo'],
        ]);

        $this->crearCampo($manager, $ducha, 'tiene_azulejo_cliente', '¿El cliente tiene azulejo?', 'boolean', false, 50);

        $this->crearCampo($manager, $ducha, 'tipo_mampara', 'Tipo de mampara', 'select', true, 60, [
            ['value' => 'frontal_fijo_corredera', 'label' => 'Frontal fijo + corredera'],
            ['value' => 'angular', 'label' => 'Angular'],
            ['value' => 'angular_doble', 'label' => 'Angular doble corredera / plegable'],
            ['value' => 'sin_mampara', 'label' => 'Sin mampara'],
        ]);

        $this->crearCampo($manager, $ducha, 'producto_plato_id', 'Modelo de plato', 'producto', false, 70);
        $this->crearCampo($manager, $ducha, 'producto_mampara_id', 'Modelo de mampara', 'producto', false, 80);

        $this->crearCampo($manager, $ducha, 'griferia', 'Grifería', 'select', true, 90, [
            ['value' => 'mantener', 'label' => 'Mantener grifería actual'],
            ['value' => 'barra_estandar', 'label' => 'Barra de ducha estándar'],
            ['value' => 'producto', 'label' => 'Elegir modelo concreto'],
        ]);

        $this->crearCampo($manager, $ducha, 'producto_griferia_id', 'Modelo de grifería', 'producto', false, 100);

        $this->crearCampo($manager, $ducha, 'observaciones', 'Observaciones internas', 'text', false, 110);

        $manager->flush();
    }

    private function crearCampo(
        ObjectManager $manager,
        PresupuestoConfigurador $configurador,
        string $codigo,
        string $etiqueta,
        string $tipoCampo,
        bool $obligatorio,
        int $orden,
        ?array $opciones = null
    ): void {
        $campo = new PresupuestoConfiguradorCampo();
        $campo->setConfigurador($configurador);
        $campo->setCodigo($codigo);
        $campo->setEtiqueta($etiqueta);
        $campo->setTipoCampo($tipoCampo);
        $campo->setObligatorio($obligatorio);
        $campo->setOrden($orden);
        $campo->setActivo(true);
        $campo->setOpciones($opciones);

        $manager->persist($campo);
    }
}