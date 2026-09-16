<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;

class DocumentoCalculatorService
{
    public function recalcularDocumento(Documento $documento): void
    {
        $baseImponible = '0.00';
        $totalIva = '0.00';
        $totalCoste = '0.00';

        foreach ($documento->getLineas() as $linea) {
            $this->recalcularLinea($linea);

            $baseImponible = bcadd($baseImponible, $linea->getSubtotal(), 2);
            $totalIva = bcadd($totalIva, $linea->getTotalIva(), 2);
            $totalCoste = bcadd($totalCoste, $linea->getTotalCoste(), 2);
        }

        $documento->setBaseImponible($baseImponible);
        $documento->setTotalIva($totalIva);
        $documento->setTotal(bcadd($baseImponible, $totalIva, 2));
        $documento->setTotalCoste($totalCoste);
    }

    public function analizarMaterialesFactura(Documento $documento): array
    {
        $baseFactura = 0.0;
        $costeMateriales = 0.0;
        $ventaMateriales = 0.0;
        $numeroMateriales = 0;
        $numeroMaterialesConCoste = 0;
        $numeroMaterialesSinCoste = 0;
        $ventaMaterialesSinCoste = 0.0;

        foreach ($documento->getLineas() as $linea) {
            if (!$linea->isFacturaObra()) {
                continue;
            }

            $subtotal = (float) $linea->getSubtotal();
            $baseFactura += $subtotal;

            if ($linea->getTipoLinea() !== 'producto') {
                continue;
            }

            $numeroMateriales++;
            $ventaMateriales += $subtotal;

            $costeUnitario = (float) $linea->getCosteUnitario();

            if ($costeUnitario <= 0.0) {
                $numeroMaterialesSinCoste++;
                $ventaMaterialesSinCoste += $subtotal;
                continue;
            }

            $numeroMaterialesConCoste++;
            $costeMateriales += (float) $linea->getCantidad() * $costeUnitario;
        }

        $porcentajeMaterialesCoste = $baseFactura > 0.0
            ? ($costeMateriales / $baseFactura) * 100
            : 0.0;
        $porcentajeMaterialesVenta = $baseFactura > 0.0
            ? ($ventaMateriales / $baseFactura) * 100
            : 0.0;

        return [
            'baseFactura' => round($baseFactura, 2),
            'costeMateriales' => round($costeMateriales, 2),
            'ventaMateriales' => round($ventaMateriales, 2),
            'porcentajeMaterialesCoste' => round($porcentajeMaterialesCoste, 2),
            'porcentajeMaterialesVenta' => round($porcentajeMaterialesVenta, 2),
            'numeroMateriales' => $numeroMateriales,
            'numeroMaterialesConCoste' => $numeroMaterialesConCoste,
            'numeroMaterialesSinCoste' => $numeroMaterialesSinCoste,
            'ventaMaterialesSinCoste' => round($ventaMaterialesSinCoste, 2),
            'costeCompleto' => $numeroMaterialesSinCoste === 0,
            'estimacionParcial' => $numeroMaterialesSinCoste > 0,
        ];
    }

    public function recalcularLinea(DocumentoLinea $linea): void
    {
        $cantidad = $this->normalizarDecimal($linea->getCantidad(), 3);
        $precioUnitario = $this->normalizarDecimal($linea->getPrecioUnitario(), 2);
        $costeUnitario = $this->normalizarDecimal($linea->getCosteUnitario(), 2);
        $descuento = $this->normalizarDecimal($linea->getDescuento(), 2);
        $tipoIva = $this->normalizarDecimal($linea->getTipoIva(), 2);

        // subtotal bruto = cantidad × precio
        $subtotalBruto = bcmul($cantidad, $precioUnitario, 4);

        // factor descuento = 1 - (descuento / 100)
        $factorDescuento = bcsub('1', bcdiv($descuento, '100', 6), 6);

        // subtotal neto
        $subtotal = bcmul($subtotalBruto, $factorDescuento, 4);

        // iva
        $totalIva = bcmul($subtotal, bcdiv($tipoIva, '100', 6), 4);

        // coste
        $totalCoste = bcmul($cantidad, $costeUnitario, 4);

        $linea->setSubtotal($this->redondear($subtotal, 2));
        $linea->setTotalIva($this->redondear($totalIva, 2));
        $linea->setTotalCoste($this->redondear($totalCoste, 2));
    }

    private function normalizarDecimal(string|int|float|null $valor, int $scale = 2): string
    {
        if ($valor === null || $valor === '') {
            return number_format(0, $scale, '.', '');
        }

        $valor = str_replace(',', '.', (string) $valor);

        return number_format((float) $valor, $scale, '.', '');
    }

    private function redondear(string $valor, int $decimales = 2): string
    {
        return number_format((float) $valor, $decimales, '.', '');
    }
}
