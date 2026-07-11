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

    public function crearDesdeJson(
        array $json,
        ?string $rutaPdf = null,
        ?string $nombreOriginal = null
    ): FacturaProveedor {
        $factura = new FacturaProveedor();

        // ---------------------------------------------------------------------
        // CABECERA
        // ---------------------------------------------------------------------

        $factura->setProveedorNombre(
            $json['empresa_emisora']['nombre'] ?? null
        );

        $factura->setNumeroFactura(
            $json['numero_factura'] ?? null
        );

        $factura->setJsonOriginal($json);
        $factura->setRutaPdf($rutaPdf);
        $factura->setNombreArchivoOriginal($nombreOriginal);
        $factura->setEstadoAsignacion('revision');

        if (!empty($json['fecha_factura'])) {
            try {
                $factura->setFechaFactura(
                    new \DateTime($json['fecha_factura'])
                );
            } catch (\Throwable) {
                $factura->setFechaFactura(null);
            }
        }

        $factura->setTotalBase(
            (float) ($json['base_imponible'] ?? 0)
        );

        $factura->setTotalIva(
            (float) ($json['iva']['importe'] ?? 0)
        );

        $factura->setTotalFactura(
            (float) ($json['total_factura'] ?? 0)
        );

        // ---------------------------------------------------------------------
        // IMPUESTOS GENERALES DE LA FACTURA
        // ---------------------------------------------------------------------

        $ivaPct = (float) ($json['iva']['porcentaje'] ?? 21);
        $rePct = (float) ($json['recargo_equivalencia']['porcentaje'] ?? 0);

        $tieneRecargo = $rePct > 0;

        $factura->setTieneRecargoEquivalencia($tieneRecargo);
        $factura->setTotalRecargoEquivalencia(
            (float) ($json['recargo_equivalencia']['importe'] ?? 0)
        );

        // ---------------------------------------------------------------------
        // LÍNEAS
        // ---------------------------------------------------------------------

        foreach (($json['articulos'] ?? []) as $articulo) {
            $linea = new FacturaProveedorLinea();

            $cantidad = (float) ($articulo['cantidad'] ?? 1);
            $precio = (float) ($articulo['precio_unitario'] ?? 0);
            $dto = (float) ($articulo['descuento_porcentaje'] ?? 0);

            $importeBruto = round(
                $precio * $cantidad * (1 - $dto / 100),
                2
            );

            $importeIva = round(
                $importeBruto * ($ivaPct / 100),
                2
            );

            $importeRe = $tieneRecargo
                ? round($importeBruto * ($rePct / 100), 2)
                : 0.0;

            $total = isset($articulo['importe_final'])
                ? (float) $articulo['importe_final']
                : round(
                    $importeBruto + $importeIva + $importeRe,
                    2
                );

            $linea->setDescripcion(
                $articulo['descripcion'] ?? null
            );

            $linea->setCantidad($cantidad);
            $linea->setPrecioUnitario($precio);

            $linea->setImporteBruto($importeBruto);
            $linea->setBase($importeBruto);

            $linea->setPorcentajeIva($ivaPct);
            $linea->setImporteIva($importeIva);

            $linea->setTieneRecargoEquivalencia($tieneRecargo);

            $linea->setPorcentajeRecargoEquivalencia(
                $tieneRecargo ? $rePct : null
            );

            $linea->setImporteRecargoEquivalencia(
                $tieneRecargo ? $importeRe : null
            );

            $linea->setTotal($total);

            $linea->setIva(null);
            $linea->setEstado('revision');
            $linea->setTipoDestino(null);

            $factura->addLinea($linea);
        }

        $this->em->persist($factura);
        $this->em->flush();

        return $factura;
    }
}