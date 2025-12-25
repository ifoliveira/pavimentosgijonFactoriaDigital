<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

class PresupuestoCalculatorService
{
    private array $precios = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    // ====================================================
    //  PUBLIC API
    // ====================================================

    public function calcular(string $tipo, array $jsonFinal): array
    {
        $this->cargarYaml($tipo);

        $total = 0;
        $manoObra = [];
        $materiales = [];
        $detalle = [];
        $vars = []; // ← variables calculadas por cada regla (m2, etc.)

        $this->logger->info("🔎 JSON FINAL RECIBIDO:", $jsonFinal);

        foreach ($this->precios['reglas'] as $regla) {

            $cond = $regla['si'] ?? null;

            if ($cond === null) {
                $this->logger->error("❌ Regla sin condición 'si': " . json_encode($regla));
                continue;
            }
            if ($this->evaluarCondicion($cond, $jsonFinal)) {

                $detalle[] = $cond;

                // =====================
                // 1) calcular:
                // =====================
                if (isset($regla['calcular'])) {

                    foreach ($regla['calcular'] as $var => $expr) {

                        $valor = $this->evaluarExpresionV2($expr, $jsonFinal, $vars);

                        $vars[$var] = $valor;

                        $this->logger->info("🧮 Variable calculada: $var = $valor");
                    }
                }

                // =====================
                // 2) sumar:
                // =====================
                if (isset($regla['sumar'])) {

                    foreach ($regla['sumar'] as $ruta => $expr) {

                        $valorRaw = $expr;
                        $this->logger->info("🧮 Valor para sumar: $valorRaw ");
                        // 1) ¿Es una ruta YAML?
                        if (is_string($valorRaw) && preg_match('/^[a-zA-Z0-9_.]+$/', $valorRaw)) {

                            // Entonces es una ruta del YAML -> hay que resolverla ANTES del eval.
                            $importe = $this->extraer($this->precios, $valorRaw);

                            // 2) descripción
                            $rutaDescripcion = preg_replace('/\.precio$/', '.descripcion', $valorRaw);
                            $descripcion = $this->extraer($this->precios, $rutaDescripcion);
                            $this->logger->info("🧮 Descripcion: $valorRaw = $descripcion");
  


                        } else {

                            // 2) Es una expresión matemática -> aquí sí evalúa
                            $importe = $this->evaluarExpresionV2($valorRaw, $jsonFinal, $vars);
                            // DESCRIPCIÓN manual para expresiones matemáticas
                            $this->logger->info("🧮 Calcula la expresión con: $ruta ");
                            $descripcion = $this->generarDescripcion($ruta);  
                                    
                             
                            $rutaConcepto =  str_replace('_', '.', $ruta);; // ya es la buena

                            $descripcion = $this->extraer(
                                $this->precios,
                                $rutaConcepto . '.descripcion'
                            );
                            $this->logger->info("🧮 Calcula la expresión devuelve: $descripcion  ");

                        }

                       
                        $this->logger->info("💰 Sumado prev :$importe");  
                        $this->acumular($manoObra, $materiales, $ruta, $importe, $descripcion);

                        $total += $importe;

                        $this->logger->info("💰 Sumado: $ruta = $importe");
                    }
                }
            }
        }

        return [
            'total' => round($total, 2),
            'mano_obra' => $manoObra,
            'materiales' => $materiales,
            'detalle' => $detalle,
        ];
    }

    // ====================================================
    //  INTERNALS
    // ====================================================

    private function cargarYaml(string $tipo): void
    {
        $ruta = __DIR__ . "/../../config/precios_{$tipo}.yaml";

        if (!file_exists($ruta)) {
            throw new \Exception("No existe el archivo $ruta");
        }

        $this->precios = Yaml::parseFile($ruta);
        $this->logger->info("📄 YAML cargado: precios_{$tipo}.yaml");
    }

    // ----------------------------------------------------

    private function evaluarCondicion(string $condicion, array $json): bool
    {
        $condicionPHP = preg_replace_callback(
            '/[a-zA-Z_][a-zA-Z0-9_\.]*/',
            function ($m) use ($json) {

                $clave = $m[0];

                // Palabras reservadas → no tocar
                if (in_array($clave, ['true','false','null','and','or','&&','||'])) {
                    return $clave;
                }

                // RUTAS ANIDADAS: zona_azulejos.altura_cm
                if (str_contains($clave, '.')) {
                    $valor = $this->extraer($json, $clave);

                    if ($valor === null) return 'null';
                    if (is_bool($valor)) return $valor ? 'true' : 'false';
                    if (is_numeric($valor)) return $valor;

                    return "'$valor'";
                }
                $this->logger->info("Clave antes de fallar: $clave");
                // CLAVES SIMPLES: accion_grifo, tipo_mampara...
                if (array_key_exists($clave, $json)) {
                    $valor = $json[$clave];

                    if (is_bool($valor)) return $valor ? 'true' : 'false';
                    if (is_numeric($valor)) return $valor;
                    return "'$valor'";
                }

                // Dejar tal cual si no existe en JSON
                return $clave;
            },
            $condicion
        );

        $this->logger->info("🧩 Condición evaluable: $condicionPHP");

        try {
            return eval("return ($condicionPHP);");
        } catch (\Throwable $e) {
            $this->logger->error("❌ Error evaluarCondicion: {$e->getMessage()}");
            return false;
        }
    }


    // ----------------------------------------------------

    private function extraer(array $json, string $ruta)
    {
        foreach (explode('.', $ruta) as $p) {
            if (!isset($json[$p])) return null;
            $json = $json[$p];
        }
        return $json;
    }

    private function generarDescripcion(string $ruta): string
    {
        // Convertimos "materiales_azulejosReposicion" en "Reposición de azulejos"
        $texto = preg_replace('/^materiales_/', '', $ruta);
        $texto = str_replace('_', ' ', $texto);
        return ucfirst($texto);
    }


    // ----------------------------------------------------

    private function evaluarExpresion(string|float|int $expr, array $json, array $vars): float
    {
        if (is_numeric($expr)) {
            return floatval($expr);
        }

        // 1. Variables del JSON
        $altura_cm = $this->extraer($json, 'zona_azulejos.altura_cm');
        $largo_cm  = $this->extraer($json, 'medida_platoducha.largo_cm');
        $ancho_cm  = $this->extraer($json, 'medida_platoducha.ancho_cm');

        // 2. Variables calculadas
        foreach ($vars as $k => $v) {
            ${$k} = $v;
        }

        // ----------------------------
        // 3. Sustituir rutas del JSON
        // ----------------------------
        $expr = str_replace('zona_azulejos.altura_cm', '$altura_cm', $expr);
        $expr = str_replace('medida_platoducha.largo_cm', '$largo_cm', $expr);
        $expr = str_replace('medida_platoducha.ancho_cm', '$ancho_cm', $expr);

        // 4. Sustituir variables calculadas
        foreach ($vars as $k => $v) {
            $expr = preg_replace('/\b' . preg_quote($k, '/') . '\b/', '$' . $k, $expr);
        }

        // -------------------------------------------
        // 5. SUSTITUIR RUTAS DEL YAML DE PRECIOS
        // -------------------------------------------
        $expr = preg_replace_callback('/materiales\.[a-zA-Z0-9_.]+/', function($m) {
            return $this->extraer($this->precios, $m[0]);
        }, $expr);

        $expr = preg_replace_callback('/manoObra\.[a-zA-Z0-9_.]+/', function($m) {
            return $this->extraer($this->precios, $m[0]);
        }, $expr);

        try {
            $valor = eval("return floatval($expr);");
            return $valor ?: 0;
        } catch (\Throwable $e) {
            $this->logger->error("❌ Error evaluarExpresion: $expr — {$e->getMessage()}");
            return 0;
        }
    }

private function evaluarExpresionV2(string|float|int $expr, array $json, array $vars): float
    {
        if (is_numeric($expr)) {
                return (float) $expr;
            }

            // 1. Sustituir variables calculadas
            foreach ($vars as $k => $v) {
                $expr = preg_replace('/\b' . preg_quote($k, '/') . '\b/', $v, $expr);
            }

            // 2. Sustituir rutas del JSON dinámicamente
            $expr = preg_replace_callback(
                '/([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+)+)/',
                function ($m) use ($json) {
                    $valor = $this->extraer($json, $m[1]);
                    return $valor !== null ? $valor : $m[0];
                },
                $expr
            );

            // 3. Sustituir precios materiales
            $expr = preg_replace_callback(
                '/materiales\.[a-zA-Z0-9_.]+/',
                fn($m) => $this->extraer($this->precios, $m[0]) ?? 0,
                $expr
            );

            // 4. Sustituir precios mano de obra
            $expr = preg_replace_callback(
                '/manoObra\.[a-zA-Z0-9_.]+/',
                fn($m) => $this->extraer($this->precios, $m[0]) ?? 0,
                $expr
            );

            try {
                return (float) eval("return $expr;");
            } catch (\Throwable $e) {
                $this->logger->error("❌ Error evaluarExpresion2: $expr — {$e->getMessage()}");
                return 0;
            }
    }    

    // ----------------------------------------------------
        private function acumular(array &$manoObra, array &$materiales, string $ruta, float $importe, string $descripcion): void
        {
            // manoObra_base  → [manoObra][base]
            // materiales_platoMedio → [materiales][platoMedio]

            $pos = strpos($ruta, '_');
            if ($pos === false) {
                $this->logger->error("❌ Ruta inválida en acumular(): $ruta");
                return;
            }

            $seccion = substr($ruta, 0, $pos);     // manoObra / materiales
            $clave   = substr($ruta, $pos + 1);    // base / platoMedio / griferiaBarra...
 
            // MANO DE OBRA
            if ($seccion === 'manoObra') {
                // ❗ la descripción viene de $detalles
                $manoObra[$descripcion] = ($manoObra[$descripcion] ?? 0) + $importe;
      
                return;
            }

            // MATERIALES
            if ($seccion === 'materiales') {
                $materiales[$descripcion] = ($materiales[$descripcion] ?? 0) + $importe;
      
                return;
            }

            $this->logger->error("❌ Sección '$seccion' desconocida en ruta '$ruta'");
        }

}
