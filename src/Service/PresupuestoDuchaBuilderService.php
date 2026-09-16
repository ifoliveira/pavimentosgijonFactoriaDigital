<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Documento;
use App\Entity\DocumentoConfiguracion;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use App\Service\Documento\DocumentoLineaService;

final class PresupuestoDuchaBuilderService
{
    public function __construct(
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        private readonly DocumentoLineaService $documentoLineaService,
    ) {
    }

    public function generar(
        Documento $documento,
        DocumentoConfiguracion $configuracion
    ): void {
        $datos = $configuracion->getDatos();

        $configurador = $this->budgetFlowConfiguratorService
            ->obtenerConfiguradorParaFormulario('conjunto_ducha');

        $valores = $this->mapearValoresBudgetFlow($datos);

        $valores = $this->budgetFlowConfiguratorService
            ->normalizarValores(
                $configurador,
                $valores
            );

        $validacion = $this->budgetFlowConfiguratorService
            ->validar(
                $configurador,
                $valores
            );

        if (!($validacion['valido'] ?? false)) {
            throw new \RuntimeException(
                'La configuración de BudgetFlow no es válida: '
                . json_encode(
                    $validacion['errores'] ?? [],
                    JSON_UNESCAPED_UNICODE
                )
            );
        }

        $resultado = $this->budgetFlowConfiguratorService
            ->generar(
                $configurador,
                $valores
            );

        $lineas = $resultado['lineas'] ?? [];

        if (!$lineas) {
            throw new \RuntimeException(
                'BudgetFlow no ha generado ninguna línea para la configuración.'
            );
        }

        /*
        * Solo borramos las líneas antiguas después
        * de haber obtenido correctamente las nuevas.
        */
        $this->documentoLineaService->eliminarLineasDocumentoPorOrigenes(
            documento: $documento,
            origenes: [
                'configurador',
                'configurador_estimado',
            ],
            flush: false,
        );

        foreach ($lineas as $linea) {
            $this->crearLineaBudgetFlow(
                documento: $documento,
                linea: $linea,
            );
        }

        $this->documentoLineaService
            ->recalcularDocumentoCompleto(
                $documento,
                flush: true,
            );
    }

    private function crearLineaBudgetFlow(
        Documento $documento,
        array $linea
    ): void {
        $cantidad = (float) ($linea['cantidad'] ?? 1);

        $precioSinIva = (float) (
            $linea['precioUnitarioSinIva'] ?? 0
        );

        $tipoIva = (float) (
            $linea['tipoIva'] ?? 21
        );

        $tipoLinea = $this->resolverTipoLinea($linea);

        if (in_array($tipoLinea, ['servicio', 'mano_obra', 'descuento'], true)) {
            $tipoIva = $this->documentoLineaService
                ->resolverTipoIvaParaNuevaLineaFactura($documento);
        }

        $precioConIva = round(
            $precioSinIva * (1 + ($tipoIva / 100)),
            2
        );

        $this->documentoLineaService->crearLineaDesdeConfigurador(
            documento: $documento,
            descripcion: (string) ($linea['descripcion'] ?? ''),
            cantidad: $cantidad,
            precioConIva: $precioConIva,
            costeUnitario: 0,
            tipoLinea: $tipoLinea,
            catalogoProducto: null,
            origenLinea: 'configurador',
            tipoIva: $tipoIva,
            flush: false,
        );
    }

    private function resolverTipoLinea(array $linea): string
    {
        $descripcion = mb_strtolower(
            (string) ($linea['descripcion'] ?? '')
        );

        if (
            str_contains($descripcion, 'instalación')
            || str_contains($descripcion, 'bañera por plato')
        ) {
            return 'mano_obra';
        }

        return 'producto';
    }

    private function mapearValoresBudgetFlow(array $datos): array
    {
        $largo = (float) ($datos['largo_plato'] ?? 0);
        $ancho = (float) ($datos['ancho_plato'] ?? 0);

        $valores = [
            'selector_plato_ducha' => [
                'ancho' => $ancho,
                'largo' => $largo,
            ],

            'selector_mano_obra' => [
                'tipo_trabajo' => $this->mapearManoObra(
                    $datos['alicatado'] ?? 'minimo'
                ),
            ],
        ];

        $tipoMampara = $datos['tipo_mampara'] ?? null;

        if ($tipoMampara && $tipoMampara !== 'sin_mampara') {
            $valores['selector_mampara'] = $this->mapearMampara(
                datos: $datos,
                largo: $largo,
                ancho: $ancho,
            );
        }

        $griferia = $datos['griferia'] ?? 'mantener';

        if ($griferia !== 'mantener') {
            $valores['griferia'] = $this->mapearGriferia($griferia);
        }

        return $valores;
    }

    private function mapearMampara(
        array $datos,
        float $largo,
        float $ancho
    ): array {
        return [
            'tipo_instalacion' => 'frente',
            'ancho_frente' => $largo,
            'tipo_apertura' => 'corredera',
        ];
    }


    private function mapearManoObra(string $alicatado): string
    {
        return match ($alicatado) {
            'minimo' => 'banera_plato_cenefa',
            'hasta_1m' => 'banera_plato_cenefa',
            'hasta_el_techo' => 'banera_plato_zona_ducha',

            default => 'banera_plato_cenefa',
        };
    }
        
    private function mapearGriferia(string $griferia): array
    {
        return match ($griferia) {
            'barra_estandar' => [
                'uso' => 'ducha',
                'tipo' => 'barra_monomando',
            ],

            default => [
                'uso' => 'ducha',
                'tipo' => 'monomando',
            ],
        };
    }

}
