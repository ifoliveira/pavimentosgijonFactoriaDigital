<?php

namespace App\Service\Tesoreria;

use App\Entity\BolsaGastosConfiguracion;
use App\Entity\FacturaProveedor;
use App\Repository\BolsaGastosAjusteRepository;
use App\Repository\BolsaGastosConfiguracionRepository;
use App\Repository\BolsaGastosOrigenRepository;

/** Proyección reproducible: no modifica gastos de obra ni genera Forecast. */
class BolsaGastosService
{
    public function __construct(
        private readonly BolsaGastosConfiguracionRepository $configuraciones,
        private readonly BolsaGastosOrigenRepository $origenes,
        private readonly BolsaGastosAjusteRepository $ajustes,
        private readonly BolsaGastosCalculator $calculator,
    ) {
    }

    public function getConfiguracion(): BolsaGastosConfiguracion
    {
        return $this->configuraciones->obtener();
    }

    /** @return array<string, mixed> */
    public function getResumen(?\DateTimeImmutable $hasta = null): array
    {
        $hasta = ($hasta ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $config = $this->getConfiguracion();
        $inicio = $config->getFechaInicio();
        $activo = $inicio !== null && $inicio <= $hasta;
        $eventos = [];
        $incidencias = [];
        $sinVencimiento = [];
        $facturasExcluidas = 0;

        if ($activo) {
            // Las líneas del presupuesto aceptado son inmutables. No usamos los
            // ProyectoGasto, cuyos estados/importes cambian al asignar facturas.
            foreach ($this->origenes->findPresupuestos($inicio, $hasta) as $presupuesto) {
                $fecha = $presupuesto->getFechaAceptacion();
                if ($fecha === null || !$this->enPeriodo($fecha, $inicio, $hasta)) {
                    continue;
                }
                $fecha = \DateTimeImmutable::createFromInterface($fecha)->setTime(0, 0);
                $base = 0;
                foreach ($presupuesto->getLineas() as $linea) {
                    $base += self::centimos(bcmul($linea->getCosteUnitario(), $linea->getCantidad(), 6));
                }
                if ($base === 0) {
                    continue;
                }
                $finPrevisto = $presupuesto->getProyecto()?->getFechaFinPrevista();
                $fechaPrevista = $finPrevisto !== null
                    ? \DateTimeImmutable::createFromInterface($finPrevisto)
                    : $fecha->modify(sprintf('+%d days', $config->getPlazoObraDias()));
                $fechaPrevista = max($fecha, $fechaPrevista)->modify(sprintf('+%d days', $config->getPlazoPagoDias()));
                $eventos[] = [
                    'id' => 'presupuesto:'.$presupuesto->getId(),
                    'tipo' => 'presupuesto',
                    'fecha' => $fecha,
                    'fechaPrevista' => $fechaPrevista->setTime(0, 0),
                    'importeCentimos' => $base,
                    'concepto' => $presupuesto->getNumeroFormateado().' · '.($presupuesto->getProyecto()?->getNombre() ?? 'Proyecto'),
                    'documentoId' => $presupuesto->getId(),
                    'proyectoId' => $presupuesto->getProyecto()?->getId(),
                ];
            }

            $excluidos = array_map(self::normalizarProveedor(...), $config->getProveedoresExcluidos());
            foreach ($this->origenes->findFacturas($inicio, $hasta) as $factura) {
                $fecha = $factura->getFechaFactura();
                if ($fecha === null || !$this->enPeriodo($fecha, $inicio, $hasta)) {
                    continue;
                }
                $fecha = \DateTimeImmutable::createFromInterface($fecha)->setTime(0, 0);
                $concepto = ($factura->getProveedorNombre() ?: 'Proveedor').' · '.($factura->getNumeroFactura() ?: '#'.$factura->getId());

                // Una factura recibida puede no tener aún todos sus vencimientos.
                // Su diferencia sigue contando como pago, con fecha estimada,
                // incluso cuando el proveedor está excluido del consumo de bolsa.
                $pendienteSinVencimiento = $this->sinVencimiento($factura);
                if ($pendienteSinVencimiento > 0) {
                    $sinVencimiento[] = [
                        'factura' => $factura,
                        'importe' => $pendienteSinVencimiento / 100,
                        'fecha' => max($hasta, $fecha->modify(sprintf('+%d days', $config->getPlazoPagoDias()))),
                    ];
                }

                if (in_array(self::normalizarProveedor((string) $factura->getProveedorNombre()), $excluidos, true)) {
                    ++$facturasExcluidas;
                    continue;
                }
                $base = $factura->getTotalBase();
                if ($base === null || !is_finite($base)) {
                    $incidencias[] = $concepto.': falta una base válida; no se descuenta de la bolsa.';
                    continue;
                }
                $baseCentimos = self::centimos($base);
                if ($baseCentimos === 0) {
                    if (($factura->getTotalFactura() ?? 0) != 0) {
                        $incidencias[] = $concepto.': base cero; revisa el importe antes de descontarlo de la bolsa.';
                    }
                    continue;
                }
                $total = $factura->getTotalFactura();
                if ($total === null || !is_finite($total) || $baseCentimos * $total <= 0) {
                    $incidencias[] = $concepto.': falta un total coherente; se conserva la previsión hasta revisar la factura.';
                    continue;
                }
                $eventos[] = [
                    'id' => 'factura:'.$factura->getId(),
                    'tipo' => 'factura',
                    'fecha' => $fecha,
                    'fechaPrevista' => $fecha,
                    'importeCentimos' => $baseCentimos,
                    'concepto' => $concepto,
                    'proveedor' => $factura->getProveedorNombre(),
                    'facturaId' => $factura->getId(),
                ];
            }

            foreach ($this->ajustes->findHasta($inicio, $hasta) as $ajuste) {
                if (!$this->enPeriodo($ajuste->getFecha(), $inicio, $hasta)) {
                    continue;
                }
                $eventos[] = [
                    'id' => 'ajuste:'.$ajuste->getId(),
                    'tipo' => 'ajuste',
                    'fecha' => $ajuste->getFecha(),
                    'fechaPrevista' => $ajuste->getFecha()->modify(sprintf('+%d days', $config->getPlazoPagoDias())),
                    'importeCentimos' => self::centimos($ajuste->getImporteBase()),
                    'concepto' => $ajuste->getMotivo(),
                ];
            }
        }

        $resultado = $this->calculator->calcular($eventos);
        foreach ($resultado['movimientos'] as $movimiento) {
            if (!empty($movimiento['incidencia'])) {
                $incidencias[] = $movimiento['concepto'].': '.$movimiento['incidencia'];
            }
        }
        $pendientes = [];
        $ivaCentimos = 0;
        foreach ($resultado['pendientes'] as $lote) {
            $lote['fechaPrevista'] = max($hasta, $lote['fechaPrevista']);
            $lote['ivaCentimos'] = (int) round($lote['restanteCentimos'] * (float) $config->getIvaEstimado() / 100);
            $lote['totalCentimos'] = $lote['restanteCentimos'] + $lote['ivaCentimos'];
            $ivaCentimos += $lote['ivaCentimos'];
            $pendientes[] = $lote;
        }

        return [
            'activo' => $activo,
            'configuracion' => $config,
            'fechaCorte' => $hasta,
            'saldoBase' => $resultado['saldoCentimos'] / 100,
            'saldoIva' => $ivaCentimos / 100,
            'saldoConIva' => ($resultado['saldoCentimos'] + $ivaCentimos) / 100,
            'aportacionesBase' => $resultado['aportacionesCentimos'] / 100,
            'consumidoBase' => $resultado['consumidoCentimos'] / 100,
            'ajustesBase' => $resultado['ajustesCentimos'] / 100,
            'excesoFacturasBase' => $resultado['excesoFacturasCentimos'] / 100,
            'movimientos' => $resultado['movimientos'],
            'pendientes' => $pendientes,
            'incidencias' => $incidencias,
            'facturasSinVencimiento' => $sinVencimiento,
            'facturasExcluidas' => $facturasExcluidas,
        ];
    }

    private function sinVencimiento(FacturaProveedor $factura): int
    {
        $total = $factura->getTotalFactura();
        if ($total === null || !is_finite($total) || $total <= 0) {
            return 0;
        }
        $cubierto = 0;
        foreach ($factura->getForecasts() as $forecast) {
            if (in_array($forecast->getEstadoFr(), ['P', 'C'], true)) {
                $cubierto += max(0, -self::centimos($forecast->getImporteFr() ?? 0));
            }
        }

        return max(0, self::centimos($total) - $cubierto);
    }

    private function enPeriodo(\DateTimeInterface $fecha, \DateTimeImmutable $inicio, \DateTimeImmutable $hasta): bool
    {
        $dia = $fecha->format('Y-m-d');
        return $dia >= $inicio->format('Y-m-d') && $dia <= $hasta->format('Y-m-d');
    }

    private static function centimos(string|float|int $importe): int
    {
        return (int) round((float) $importe * 100, 0, PHP_ROUND_HALF_UP);
    }

    private static function normalizarProveedor(string $nombre): string
    {
        $nombre = mb_strtolower(trim($nombre));
        $nombre = strtr($nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        return preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;
    }
}
