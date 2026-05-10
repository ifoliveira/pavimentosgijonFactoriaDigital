<?php

namespace App\Service\FacturaProveedor;

use App\Entity\FacturaProveedor;
use App\Entity\FacturaProveedorLinea;
use Doctrine\ORM\EntityManagerInterface;


class FacturaProveedorService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function crearDesdeJson(array $json, ?string $rutaPdf = null, ?string $nombreOriginal = null): FacturaProveedor
    {
        $factura = new FacturaProveedor();

        // ---------------------------------------------------------------------
        // CABECERA
        // ---------------------------------------------------------------------

        $factura->setProveedorNombre($json['empresa_emisora']['nombre'] ?? null);
        $factura->setNumeroFactura($json['numero_factura'] ?? null);
        $factura->setJsonOriginal($json);
        $factura->setRutaPdf($rutaPdf);
        $factura->setNombreArchivoOriginal($nombreOriginal);
        $factura->setEstadoAsignacion('revision');        

        if (!empty($json['fecha_factura'])) {
            try {
                $factura->setFechaFactura(new \DateTime($json['fecha_factura']));
            } catch (\Throwable) {
                $factura->setFechaFactura(null);
            }
        }

        $factura->setTotalBase((float) ($json['base_imponible'] ?? 0));
        $factura->setTotalIva((float) ($json['iva']['importe'] ?? 0));
        $factura->setTotalFactura((float) ($json['total_factura'] ?? 0));

        // ---------------------------------------------------------------------
        // LÍNEAS
        // ---------------------------------------------------------------------

        foreach (($json['articulos'] ?? []) as $articulo) {

            $linea = new FacturaProveedorLinea();

            $cantidad = (float) ($articulo['cantidad'] ?? 1);
            $precio   = (float) ($articulo['precio_unitario'] ?? 0);
            $dto      = (float) ($articulo['descuento_porcentaje'] ?? 0);
            $ivaPct = (float) ($json['iva']['porcentaje'] ?? 21);
            $rePct = (float) ($json['recargo_equivalencia']['porcentaje'] ?? 0);              

            // ✅ Cálculo correcto de base
            $baseCalculada = $precio * $cantidad * (1 - $dto / 100);

            // ✅ SIEMPRE manda el importe_final si viene
            $total = isset($articulo['importe_final'])
                ? (float) $articulo['importe_final']
                : $baseCalculada;

            $linea->setDescripcion($articulo['descripcion'] ?? null);
            $linea->setCantidad($cantidad);
            $linea->setPrecioUnitario($precio);
          

            // 🔥 IMPORTANTE: base ≠ total
            $importeBruto = $baseCalculada;
            $importeIva = round($importeBruto * ($ivaPct / 100), 2);

            $tieneRe = $rePct > 0;
            $importeRe = $tieneRe ? round($importeBruto * ($rePct / 100), 2) : 0;

            $total = isset($articulo['importe_final'])
                ? (float) $articulo['importe_final']
                : round($importeBruto + $importeIva + $importeRe, 2);

            $linea->setImporteBruto($importeBruto);
            $linea->setBase($importeBruto);

            $linea->setPorcentajeIva($ivaPct);
            $linea->setImporteIva($importeIva);

            $linea->setTieneRecargoEquivalencia($tieneRe);
            $linea->setPorcentajeRecargoEquivalencia($rePct ?: null);
            $linea->setImporteRecargoEquivalencia($importeRe ?: null);

            $linea->setTotal($total);
            $linea->setEstado('revision');

            // De momento no usamos IVA por línea
            $linea->setIva(null);

            // Estado inicial
            $linea->setEstado('revision');
            $linea->setTipoDestino(null);

            $factura->addLinea($linea);
        }

        $this->em->persist($factura);
        $this->em->flush();

        return $factura;
    }
}