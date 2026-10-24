<?php

namespace App\Service\FacturaProveedor;

use App\Entity\FacturaProveedorLinea;
use App\Entity\ProyectoGasto;
use App\Repository\ProyectoGastoRepository;

/**
 * Buscador determinista y explicable. No confirma ni persiste nada: solamente
 * propone gastos previstos de proyectos que siguen abiertos.
 */
final class BuscadorGastoPrevistoService
{
    public function __construct(private readonly ProyectoGastoRepository $gastos)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function buscar(FacturaProveedorLinea $linea): array
    {
        $baseFactura = $linea->getImporteBruto() ?? $linea->getBase();
        if ($baseFactura === null || (float) $baseFactura <= 0) {
            return [];
        }

        $descripcion = $this->palabras((string) $linea->getDescripcion());
        if ($descripcion === []) {
            return [];
        }

        $proveedorFactura = $this->normalizar((string) $linea->getFacturaProveedor()?->getProveedorNombre());
        $candidatos = [];

        foreach ($this->gastos->findPrevistosDeProyectosAbiertos() as $gasto) {
            $palabrasGasto = $this->palabras((string) $gasto->getConcepto());
            $comunes = array_values(array_intersect($descripcion, $palabrasGasto));
            $union = array_unique([...$descripcion, ...$palabrasGasto]);
            $similitud = $union === [] ? 0.0 : count($comunes) / count($union);

            // Una descripción genérica nunca debe ser suficiente para proponer.
            if ($similitud < 0.20 || count($comunes) === 0) {
                continue;
            }

            $puntuacion = (int) round($similitud * 70);
            $motivos = [sprintf('%d término(s) relevante(s) coinciden', count($comunes))];
            $proveedorGasto = $this->normalizar((string) $gasto->getProveedor());

            if ($proveedorFactura !== '' && $proveedorGasto !== '' && $proveedorFactura === $proveedorGasto) {
                $puntuacion += 20;
                $motivos[] = 'proveedor coincidente';
            }

            $previsto = (float) ($gasto->getBasePrevista() ?? $gasto->getImportePrevisto());
            $diferencia = (float) $baseFactura - $previsto;
            if ($previsto > 0 && abs($diferencia) / $previsto <= 0.15) {
                $puntuacion += 10;
                $motivos[] = 'importe sin IVA cercano';
            }

            $candidatos[] = [
                'gasto' => $gasto,
                'puntuacion' => min($puntuacion, 100),
                'motivos' => $motivos,
                'importeFacturaBase' => (float) $baseFactura,
                'importePrevistoBase' => $previsto,
                'diferencia' => $diferencia,
            ];
        }

        usort($candidatos, static fn (array $a, array $b) => $b['puntuacion'] <=> $a['puntuacion']);

        foreach ($candidatos as $posicion => &$candidato) {
            $siguiente = $candidatos[$posicion + 1]['puntuacion'] ?? 0;
            $candidato['confianza'] = $candidato['puntuacion'] >= 70
                && ($posicion !== 0 || $candidato['puntuacion'] - $siguiente >= 15)
                ? 'alta'
                : ($candidato['puntuacion'] >= 45 ? 'media' : 'baja');
        }
        unset($candidato);

        return array_slice($candidatos, 0, 5);
    }

    /** @return array<int, string> */
    private function palabras(string $valor): array
    {
        $valor = $this->normalizar($valor);
        $stopWords = ['de', 'del', 'la', 'el', 'los', 'las', 'para', 'con', 'por', 'una', 'uno', 'uds', 'unidad'];

        return array_values(array_filter(
            preg_split('/[^a-z0-9]+/u', $valor) ?: [],
            static fn (string $palabra) => mb_strlen($palabra) >= 3 && !in_array($palabra, $stopWords, true)
        ));
    }

    private function normalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        return strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }
}
