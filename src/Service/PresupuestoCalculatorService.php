<?php

namespace App\Service;

use App\Repository\PrecioRepository;

class PresupuestoCalculatorService
{
    public function __construct(private PrecioRepository $precios) {}

    public function calcular(string $tipo, array $d): array
    {
        return match($tipo) {
            'ducha'         => $this->calcularDucha($d, $tipo),
            'baño_completo' => $this->calcularBanoCompleto($d, $tipo),
            default         => throw new \InvalidArgumentException("Tipo desconocido: $tipo"),
        };
    }

    private function p(string $clave, string $tipo): float
    {
        return $this->precios->get($clave, $tipo);
    }

    private function calcularDucha(array $d, string $tipo): array
    {
        $mo  = [];
        $mat = [];

        // Base
        $mo['Albañil']   = $this->p('albanil_base',   $tipo);
        $mo['Fontanero'] = $this->p('fontanero_base',  $tipo);

        // Alicatado
        $clave = match($d['zona_azulejos']['categoria'] ?? '') {
            'minimo'         => 'albanil_minimo',
            'hasta_1m'       => 'albanil_hasta1m',
            'hasta_el_techo' => 'albanil_hasta_techo',
            'personalizado'  => 'albanil_personalizado',
            default          => null,
        };
        if ($clave) $mo['Alicatado'] = $this->p($clave, $tipo);

        // Escayola
        if (isset($d['mantener_escayola'])) {
            $mo['Escayolista'] = $this->p('escayolista_base', $tipo);
        }

        // Plato
        $largo = $d['medida_platoducha']['largo_cm'] ?? 0;
        $clavePlato = match(true) {
            $largo < 140  => 'plato_pequeno',
            $largo <= 160 => 'plato_medio',
            default       => 'plato_grande',
        };
        $mat['Plato de ducha'] = $this->p($clavePlato, 'todos');

        // Cola
        $mat['Cola H40'] = $this->p('cola_h40', 'todos');

        // Reposición azulejos
        if ($d['reposicion_azulejos'] ?? false) {
            $alturaM = ($d['zona_azulejos']['altura_cm'] ?? 30) / 100;
            $largoM  = ($d['medida_platoducha']['largo_cm'] ?? 0) / 100;
            $anchoM  = ($d['medida_platoducha']['ancho_cm'] ?? 0) / 100;
            $m2      = round($alturaM * ($largoM + $anchoM * 2), 2);
            $mat["Reposición azulejos ({$m2} m²)"] =
                round($m2 * $this->p('azulejo_reposicion_m2', 'todos'));
        }

        // Mampara
        $mat['Mampara']            = $this->p($this->claveMampara($d), 'todos');
        $mo['Instalación mampara'] = $this->p('colocador_mampara', $tipo);

        // Grifería
        if ($d['accion_grifo'] ?? false) {
            $mat['Grifería'] = $this->p($this->claveGriferia($d), 'todos');
        }

        return $this->resultado($mo, $mat);
    }

    private function calcularBanoCompleto(array $d, string $tipo): array
    {
        $mo  = [];
        $mat = [];

        // Base
        $mo['Albañil']      = $this->p('albanil_base',        $tipo);
        $mo['Fontanero']    = $this->p('fontanero_base',      $tipo);
        $mo['Electricista'] = $this->p('electricidad_base',   $tipo);
        $mo['Pintura']      = $this->p('escayolista_pintura', $tipo);

        // Alicatado
        $clave = match($d['zona_azulejos']['categoria'] ?? '') {
            'hasta_1m'       => 'albanil_hasta1m',
            'hasta_el_techo' => 'albanil_hasta_techo',
            'personalizado'  => 'albanil_alicatado_m2',
            default          => null,
        };
        if ($clave) $mo['Alicatado'] = $this->p($clave, $tipo);

        // Escayola
        if (isset($d['mantener_escayola'])) {
            $mo['Escayolista'] = $this->p('escayolista_base', $tipo);
        }

        // Azulejos por m²
        $alto  = ($d['medida_bano']['alto_cm']  ?? 240) / 100;
        $largo = ($d['medida_bano']['largo_cm'] ?? 0)   / 100;
        $ancho = ($d['medida_bano']['ancho_cm'] ?? 0)   / 100;
        $m2    = round(($largo * $ancho) + ($largo * $alto * 2) + ($ancho * $alto * 2), 2);
        $mat["Azulejos ({$m2} m²)"] =
            round($m2 * $this->p('azulejo_reposicion_m2', 'todos'));

        // Cola
        $mat['Cola H40'] = $this->p('cola_h40', 'todos');

        // Plato
        if (($d['banera_o_ducha']['tipo'] ?? 'ducha') === 'ducha') {
            $largoB     = $d['banera_o_ducha']['largo_cm'] ?? 120;
            $clavePlato = match(true) {
                $largoB < 140  => 'plato_pequeno',
                $largoB <= 160 => 'plato_medio',
                default        => 'plato_grande',
            };
            $mat['Plato de ducha'] = $this->p($clavePlato, 'todos');
        }

        // Mampara
        $mat['Mampara']            = $this->p($this->claveMampara($d), 'todos');
        $mo['Instalación mampara'] = $this->p('colocador_mampara', $tipo);

        // Grifería
        $mat['Grifería ducha']  = $this->p($this->claveGriferia($d), 'todos');
        $mat['Grifería lavabo'] = $this->p('griferia_lavabo', 'todos');

        // Inodoro
        $mat['Inodoro'] = $this->p('inodoro_convencional', 'todos');

        // Bidé / higiénico
        match($d['bide_o_higienico'] ?? 'ninguno') {
            'bide'      => $mat['Bidé']            = $this->p('bide_convencional',  'todos'),
            'higienico' => $mat['Grupo higiénico'] = $this->p('griferia_higienico', 'todos'),
            default     => null,
        };

        // Mueble
        if ($d['mueble_bano']['quiere'] ?? false) {
            $claveMueble = match($d['mueble_bano']['medida_cm'] ?? 80) {
                90      => 'mueble_90cm',
                100     => 'mueble_100cm',
                default => 'mueble_80cm',
            };
            $mat['Mueble de baño'] = $this->p($claveMueble, 'todos');
        }

        // Iluminación y radiador
        $mat['Iluminación LED']   = $this->p('iluminacion_led',  'todos');
        $mat['Radiador toallero'] = $this->p('radiador_simple',  'todos');

        return $this->resultado($mo, $mat);
    }

    private function claveMampara(array $d): string
    {
        return match($d['tipo_mampara'] ?? '') {
            'Fijo'             => 'mampara_fija',
            'Fijo + corredera' => 'mampara_fijo_corredera',
            'Angular'          => 'mampara_angular',
            'Doble corredera'  => 'mampara_doble_corredera',
            default            => 'mampara_fijo_corredera',
        };
    }

    private function claveGriferia(array $d): string
    {
        return match($d['tipo_griferia'] ?? 'normal') {
            'barra'        => 'griferia_barra',
            'termostatica' => 'griferia_termostatica',
            default        => 'griferia_normal',
        };
    }

    private function resultado(array $mo, array $mat): array
    {
        $mo    = array_filter($mo,  fn($v) => $v > 0);
        $mat   = array_filter($mat, fn($v) => $v > 0);
        $total = array_sum($mo) + array_sum($mat);

        return [
            'total'      => round($total, 2),
            'min'        => round($total * 0.95, 2),
            'max'        => round($total * 1.05, 2),
            'mano_obra'  => $mo,
            'materiales' => $mat,
        ];
    }
}