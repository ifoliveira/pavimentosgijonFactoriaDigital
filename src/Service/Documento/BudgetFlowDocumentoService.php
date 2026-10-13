<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use Doctrine\ORM\EntityManagerInterface;

final class BudgetFlowDocumentoService
{
    public function __construct(
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        private readonly DocumentoLineaService $lineaService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function preparar(string $codigo, array $valores, array $componentesExcluidos = []): array
    {
        $configurador = $this->budgetFlowConfiguratorService->obtenerConfiguradorParaFormulario($codigo);
        $valores = $this->budgetFlowConfiguratorService->normalizarValores($configurador, $valores, $componentesExcluidos);
        $validacion = $this->budgetFlowConfiguratorService->validar($configurador, $valores);

        return [
            'configurador' => $configurador,
            'valores' => $valores,
            'validacion' => $validacion,
            'solicitud' => $this->budgetFlowConfiguratorService->construirSolicitudApi($configurador, $valores),
        ];
    }

    public function generarEIncorporar(
        Documento $documento,
        string $codigo,
        array $valores,
        array $componentesExcluidos = [],
        bool $reemplazarLineasBudgetFlow = false
    ): array {
        $preparacion = $this->preparar($codigo, $valores, $componentesExcluidos);

        if (!(bool) ($preparacion['validacion']['valido'] ?? false)) {
            return [
                ...$preparacion,
                'generado' => false,
                'lineas_incorporadas' => 0,
            ];
        }

        $resultado = $this->budgetFlowConfiguratorService->generar($preparacion['configurador'], $preparacion['valores']);
        $lineas = is_array($resultado['lineas'] ?? null) ? $resultado['lineas'] : [];

        if ($lineas === []) {
            return [
                ...$preparacion,
                'resultado' => $resultado,
                'generado' => false,
                'lineas_incorporadas' => 0,
                'error' => 'BudgetFlow no ha generado líneas.',
            ];
        }

        $incorporadas = $this->incorporarLineas($documento, $lineas, $reemplazarLineasBudgetFlow);

        return [
            ...$preparacion,
            'resultado' => $resultado,
            'generado' => true,
            'lineas_incorporadas' => $incorporadas,
        ];
    }

    public function incorporarLineas(Documento $documento, array $lineas, bool $reemplazarLineasBudgetFlow = false): int
    {
        return $this->em->wrapInTransaction(function () use ($documento, $lineas, $reemplazarLineasBudgetFlow): int {
            if ($reemplazarLineasBudgetFlow) {
                $this->lineaService->eliminarLineasDocumentoPorOrigenes($documento, ['budgetflow'], false);
            }

            $incorporadas = 0;

            foreach ($lineas as $linea) {
                if (!is_array($linea)) {
                    continue;
                }

                $descripcion = trim((string) ($linea['descripcion'] ?? ''));

                if ($descripcion === '') {
                    continue;
                }

                $cantidad = (float) ($linea['cantidad'] ?? 1);
                $destinoFacturacion = (string) (
                    $linea['destinoFacturacion']
                    ?? $linea['destino_facturacion']
                    ?? DocumentoLinea::DESTINO_FACTURA_OBRA
                );
                $tipoIva = $destinoFacturacion === DocumentoLinea::DESTINO_FACTURA_OBRA
                    ? $this->lineaService->resolverTipoIvaParaNuevaLineaFactura($documento)
                    : (float) ($linea['tipoIva'] ?? $linea['tipo_iva'] ?? 21.0);
                $precioSinIva = (float) ($linea['precioUnitarioSinIva'] ?? $linea['precio_unitario_sin_iva'] ?? 0);
                $costeUnitarioSinIva = (float) ($linea['costeUnitarioSinIva'] ?? $linea['coste_unitario_sin_iva'] ?? 0);
                $precioConIva = $precioSinIva * (1 + ($tipoIva / 100));
                $tipoLinea = $this->lineaService->resolverTipoLineaBudgetFlow($linea['tipo'] ?? null, 'producto');

                $this->lineaService->crearLinea(
                    documento: $documento,
                    descripcion: $descripcion,
                    coste: $costeUnitarioSinIva,
                    cantidad: $cantidad,
                    precio: $precioConIva,
                    descuento: 0.0,
                    productoId: null,
                    lineaId: 0,
                    tipo: $tipoLinea,
                    destinoFacturacion: $destinoFacturacion,
                    origenLinea: 'budgetflow',
                    tipoIva: $tipoIva
                );

                $incorporadas++;
            }

            $this->lineaService->recalcularDocumentoCompleto($documento, true);

            return $incorporadas;
        });
    }
}
