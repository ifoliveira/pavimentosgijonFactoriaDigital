<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class MotorDePasosService
{
    private array $config;

    public function __construct()
    {
        $this->config = Yaml::parseFile(__DIR__ . '/../../config/presupuestos.yaml')['presupuestos'];

    }

    public function getYaml()
    {
        return $this->config;
    }
          

    public function obtenerPaso(string $tipo, int $indice): ?array
    {
        return $this->config[$tipo]['pasos'][$indice] ?? null;
    }

    public function totalPasos(string $tipo): int
    {
        return count($this->config[$tipo]['pasos']);
    }

    public function obtenerPasoCondicionado(string $tipo, int $pasoIndex, array $jsonActual): ?array
    {
        $pasos = $this->config[$tipo]['pasos'] ?? [];

        if (!isset($pasos[$pasoIndex])) {
            return null;
        }

        $paso = $pasos[$pasoIndex];

        // No tiene condiciones → mostrar
        if (!isset($paso['condiciones'])) {
            return [
                'index' => $pasoIndex,
                'config' => $paso
            ];
        }

        // Evaluar condiciones
        foreach ($paso['condiciones'] as $clave => $valoresPermitidos) {

            $valor = $this->extraerValor($jsonActual, $clave);

            if (!in_array($valor, $valoresPermitidos, true)) {
                // No cumple → saltamos al siguiente paso
                return $this->obtenerPasoCondicionado($tipo, $pasoIndex + 1, $jsonActual);
            }
        }

        return [
            'index' => $pasoIndex,
            'config' => $paso
        ];
    }


    public function resolverPregunta(array $paso, array $jsonActual): string
    {
        // 1) Si es tipo condicional → prioridad máxima
        if (($paso['tipo'] ?? null) === 'opciones_condicionales' && isset($paso['textos'])) {

            $entre = $jsonActual['entre_paredes'] ?? null;


            if ($entre === true) {
                return $paso['textos']['si_entre_paredes'];
            }

            return $paso['textos']['no_entre_paredes'];
        }

        // 2) Si tiene pregunta normal → usarla
        if (isset($paso['pregunta'])) {
            return $paso['pregunta'];
        }

        // 3) Fallback
        return "Indica un detalle más para continuar";
    }



        public function resolverOpciones(array $paso, array $jsonActual): array
        {
            if (!isset($paso['opciones'])) {
                return [];
            }

            $finales = [];

            foreach ($paso['opciones'] as $id => $opcion) {

                // OPCIÓN SIMPLE (string)
                if (is_string($opcion)) {
                    $finales[$opcion] = [
                        'label' => $opcion,
                        'tipo'  => 'simple'
                    ];
                    continue;
                }

                // OPCIÓN COMPLEJA con label + condiciones
                if (is_array($opcion)) {

                    // Sin condiciones → siempre válida
                    if (!isset($opcion['condiciones'])) {
                        $finales[$id] = [
                            'label' => $opcion['label'],
                            'tipo'  => 'compleja'
                        ];
                        continue;
                    }

                    // Con condiciones → validar
                    $valida = true;

                    foreach ($opcion['condiciones'] as $clave => $valoresValidos) {

                        if (!isset($jsonActual[$clave]) ||
                            !in_array($jsonActual[$clave], $valoresValidos)) {
                            $valida = false;
                            break;
                        }
                    }

                    if ($valida) {
                        $finales[$id] = [
                            'label' => $opcion['label'],
                            'tipo'  => 'compleja'
                        ];
                    }
                }
            }

            return $finales;
        }

    private function extraerValor(array $json, string $ruta)
    {
        $partes = explode('.', $ruta);

        $actual = $json;

        foreach ($partes as $p) {
            if (!isset($actual[$p])) {
                return null;
            }
            $actual = $actual[$p];
        }

        return $actual;
    }



}

?>