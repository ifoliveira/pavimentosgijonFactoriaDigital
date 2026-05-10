<?php

namespace App\Service;

use App\Entity\CatalogoProducto;
use App\Entity\Documento;
use App\Entity\DocumentoConfiguracion;
use App\Service\Documento\DocumentoLineaService;

class PresupuestoDuchaBuilderService
{
    public function __construct(
        private CatalogoProductoSelectorService $selector,
        private DocumentoLineaService $documentoLineaService,
    ) {}

    public function generar(Documento $documento, DocumentoConfiguracion $configuracion): void
    {
        $datos = $configuracion->getDatos();

        // 1. Borrar líneas anteriores usando el servicio central
        $this->documentoLineaService->eliminarLineasDocumento($documento, flush: false);

        // 2. Datos principales
        $largo = (float) ($datos['largo_plato'] ?? 0);
        $ancho = (float) ($datos['ancho_plato'] ?? 0);
        $alicatado = $datos['alicatado'] ?? 'minimo';
        $tipoMampara = $datos['tipo_mampara'] ?? null;
        $griferia = $datos['griferia'] ?? 'mantener';

        // 3. Mano de obra base
        $this->crearLineaEstimado(
            documento: $documento,
            tipoLinea: 'mano_obra',
            descripcion: 'Mano de obra cambio de bañera por plato de ducha',
            cantidad: 1,
            precioConIva: 900,
            costeUnitario: 600
        );

        // 4. Alicatado
        if ($alicatado === 'hasta_1m') {
            $this->crearLineaEstimado(
                documento: $documento,
                tipoLinea: 'mano_obra',
                descripcion: 'Alicatado zona ducha hasta 1 metro',
                cantidad: 1,
                precioConIva: 250,
                costeUnitario: 150
            );
        }

        if ($alicatado === 'hasta_techo') {
            $this->crearLineaEstimado(
                documento: $documento,
                tipoLinea: 'mano_obra',
                descripcion: 'Alicatado zona ducha hasta techo',
                cantidad: 1,
                precioConIva: 350,
                costeUnitario: 200
            );
        }

        // 5. Plato de ducha desde catálogo
        if ($largo > 0 && $ancho > 0) {
            $plato = $this->selector->buscarProductoRecomendado(
                configuradorCodigo: 'ducha',
                uso: 'plato',
                tipo: 'resina',
                largo: $largo,
                ancho: $ancho
            );

            if ($plato) {
                $this->crearLineaCatalogo(
                    documento: $documento,
                    producto: $plato,
                    tipoLinea: 'producto',
                    cantidad: 1
                );
            } else {
                $descripcionPlato = sprintf(
                    'Plato de ducha %.0f x %.0f cm — modelo pendiente de confirmar',
                    $largo,
                    $ancho
                );

                $precioPlato = $this->calcularPrecioPlatoFallback($largo, $ancho);

                $this->crearLineaEstimado(
                    documento: $documento,
                    tipoLinea: 'producto',
                    descripcion: $descripcionPlato,
                    cantidad: 1,
                    precioConIva: $precioPlato,
                    costeUnitario: $precioPlato * 0.65
                );
            }
        }

        // 6. Mampara desde catálogo
        if ($tipoMampara && $tipoMampara !== 'sin_mampara') {
            $mampara = $this->selector->buscarProductoRecomendado(
                configuradorCodigo: 'ducha',
                uso: 'mampara',
                tipo: $tipoMampara,
                largo: null,
                ancho: $largo > 0 ? $largo : null
            );

            if ($mampara) {
                $this->crearLineaCatalogo(
                    documento: $documento,
                    producto: $mampara,
                    tipoLinea: 'producto',
                    cantidad: 1
                );
            } else {
                [$descripcionMampara, $precioMampara] = $this->calcularMamparaFallback($tipoMampara);

                $this->crearLineaEstimado(
                    documento: $documento,
                    tipoLinea: 'producto',
                    descripcion: $descripcionMampara . ' — modelo pendiente de confirmar',
                    cantidad: 1,
                    precioConIva: $precioMampara,
                    costeUnitario: $precioMampara * 0.65
                );
            }

            $this->crearLineaEstimado(
                documento: $documento,
                tipoLinea: 'mano_obra',
                descripcion: 'Colocación de mampara',
                cantidad: 1,
                precioConIva: 85,
                costeUnitario: 60
            );
        }

        // 7. Grifería desde catálogo
        if ($griferia === 'barra_estandar') {
            $productoGriferia = $this->selector->buscarProductoRecomendado(
                configuradorCodigo: 'ducha',
                uso: 'griferia',
                tipo: 'barra_estandar'
            );

            if ($productoGriferia) {
                $this->crearLineaCatalogo(
                    documento: $documento,
                    producto: $productoGriferia,
                    tipoLinea: 'producto',
                    cantidad: 1
                );
            } else {
                $this->crearLineaEstimado(
                    documento: $documento,
                    tipoLinea: 'producto',
                    descripcion: 'Barra de ducha y grifería estándar — modelo pendiente de confirmar',
                    cantidad: 1,
                    precioConIva: 180,
                    costeUnitario: 115
                );
            }


        }

        // 8. Material auxiliar
        $materialAuxiliar = $this->selector->buscarProductoRecomendado(
            configuradorCodigo: 'ducha',
            uso: 'auxiliar',
            tipo: 'material_ducha'
        );

        if ($materialAuxiliar) {
            $this->crearLineaCatalogo(
                documento: $documento,
                producto: $materialAuxiliar,
                tipoLinea: 'producto',
                cantidad: 1
            );
        } else {
            $this->crearLineaEstimado(
                documento: $documento,
                tipoLinea: 'producto',
                descripcion: 'Material auxiliar, agarres, cemento cola, rejuntado y remates',
                cantidad: 1,
                precioConIva: 120,
                costeUnitario: 75
            );
        }

        // 9. Un solo recalculo final y un solo flush
        $this->documentoLineaService->recalcularDocumentoCompleto($documento, flush: true);
    }

    private function crearLineaCatalogo(
        Documento $documento,
        CatalogoProducto $producto,
        string $tipoLinea,
        float $cantidad = 1
    ): void {
        $descripcion = $producto->getNombre();

        if ($producto->getMedidaTexto()) {
            $descripcion .= ' - ' . $producto->getMedidaTexto();
        }

        $this->documentoLineaService->crearLineaDesdeConfigurador(
            documento: $documento,
            descripcion: $descripcion,
            cantidad: $cantidad,
            precioConIva: (float) $producto->getPrecioVenta(),
            costeUnitario: (float) $producto->getPrecioCoste(),
            tipoLinea: $tipoLinea,
            catalogoProducto: $producto,
            origenLinea: 'configurador',
            tipoIva: (float) $producto->getTipoIva(),
            flush: false
        );
    }

    private function crearLineaEstimado(
        Documento $documento,
        string $tipoLinea,
        string $descripcion,
        float $cantidad,
        float $precioConIva,
        float $costeUnitario,
        float $tipoIva = 21.00
    ): void {
        $this->documentoLineaService->crearLineaDesdeConfigurador(
            documento: $documento,
            descripcion: $descripcion,
            cantidad: $cantidad,
            precioConIva: $precioConIva,
            costeUnitario: $costeUnitario,
            tipoLinea: $tipoLinea,
            catalogoProducto: null,
            origenLinea: 'configurador_estimado',
            tipoIva: $tipoIva,
            flush: false
        );
    }

    private function calcularPrecioPlatoFallback(float $largo, float $ancho): float
    {
        if ($largo <= 140) {
            return 450;
        }

        if ($largo <= 170) {
            return 520;
        }

        return 600;
    }

    private function calcularMamparaFallback(string $tipoMampara): array
    {
        return match ($tipoMampara) {
            'frontal_fijo_corredera' => ['Mampara frontal fijo + corredera', 800],
            'angular' => ['Mampara angular', 900],
            'angular_doble' => ['Mampara angular doble corredera / plegable', 1200],
            default => ['Mampara de ducha', 800],
        };
    }
}