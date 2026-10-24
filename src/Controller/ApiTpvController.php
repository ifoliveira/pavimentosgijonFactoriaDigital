<?php

namespace App\Controller;

use App\Repository\ProductosRepository;
use App\Service\Venta\VentaLineaInput;
use App\Service\Venta\VentaDocumentoService;
use App\Service\Venta\VentaTpvInput;
use App\Service\Venta\RectificacionTicketService;
use App\Service\Venta\TpvOperacionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tpv')]
class ApiTpvController extends AbstractController
{
    public function __construct(
        private VentaDocumentoService $ventaDocumentoService,
        private ProductosRepository $productosRepository,
        private string $tpvApiKeys,
    ) {}

    #[Route('/ventas', name: 'api_tpv_ventas_create', methods: ['POST'])]
    public function crearVenta(Request $request): JsonResponse
    {
        $auth = $this->autorizar($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('JSON invalido.', 400);
        }

        try {
            $input = $this->mapearVentaInput($data);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if ($auth !== true && isset($auth['caja']) && $auth['caja'] !== $input->caja) {
            return $this->error('La API key no corresponde a la caja enviada.', 403);
        }

        try {
            $resultado = $this->ventaDocumentoService->crearVentaTpv($input);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return new JsonResponse($resultado->toArray(), $resultado->idempotente ? 200 : 201);
    }

    #[Route('/ventas/{uuid}/rectificaciones', name: 'api_tpv_rectificacion_create', methods: ['POST'])]
    public function rectificarVenta(Request $request, string $uuid, RectificacionTicketService $service): JsonResponse
    {
        return $this->operacionRectificativa($request, fn (array $data) => $service->rectificar($uuid, $data));
    }

    #[Route('/ventas/{uuid}/rectificaciones/{rectificacionUuid}/reembolso', name: 'api_tpv_rectificacion_reembolso', methods: ['POST'])]
    public function confirmarReembolso(Request $request, string $uuid, string $rectificacionUuid, RectificacionTicketService $service): JsonResponse
    {
        return $this->operacionRectificativa($request, fn (array $data) => $service->confirmarReembolso($uuid, $rectificacionUuid, $data));
    }

    private function operacionRectificativa(Request $request, callable $operacion): JsonResponse
    {
        $auth = $this->autorizar($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || array_is_list($data)) {
            return $this->error('Se requiere un objeto JSON.', 400);
        }
        if ($auth !== true && isset($auth['caja']) && ($data['caja'] ?? null) !== $auth['caja']) {
            return $this->error('La API key no corresponde a la caja enviada.', 403);
        }
        try {
            $resultado = $operacion($data);
            return new JsonResponse($resultado->toArray(), $resultado->idempotente ? 200 : 201);
        } catch (TpvOperacionException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'codigo' => $e->codigo,
            ], $e->status);
        }
    }

    #[Route('/productos', name: 'api_tpv_productos', methods: ['GET'])]
    public function productos(Request $request): JsonResponse
    {
        $auth = $this->autorizar($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $productos = array_map(static function ($producto): array {
            return [
                'id' => $producto->getId(),
                'codigo' => null,
                'nombre' => $producto->getDescripcionPd(),
                'descripcion' => $producto->getDescripcionPd(),
                'familia' => $producto->getTipoPdId()?->getDecripcionTp(),
                'precio' => $producto->getPvpPd(),
                'coste' => $producto->getPrecioPd(),
                'iva' => null,
                'ean' => null,
                'activo' => !$producto->isObsoleto(),
                'stock' => $producto->getStockPd(),
            ];
        }, $this->productosRepository->findCatalogoTpv());

        return new JsonResponse([
            'success' => true,
            'productos' => $productos,
        ]);
    }

    private function mapearVentaInput(array $data): VentaTpvInput
    {
        $uuid = strtolower($this->stringObligatorio($data, 'uuid'));
        if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $uuid)) {
            throw new \InvalidArgumentException('uuid no es un UUID valido.');
        }

        $fechaRaw = $this->stringObligatorio($data, 'fecha');
        try {
            $fecha = new \DateTimeImmutable($fechaRaw, new \DateTimeZone('Europe/Madrid'));
        } catch (\Exception) {
            throw new \InvalidArgumentException('fecha no es valida.');
        }

        $lineasRaw = $data['lineas'] ?? null;
        if (!is_array($lineasRaw) || $lineasRaw === []) {
            throw new \InvalidArgumentException('lineas debe contener al menos una linea.');
        }

        $lineas = [];
        foreach ($lineasRaw as $index => $lineaRaw) {
            if (!is_array($lineaRaw)) {
                throw new \InvalidArgumentException(sprintf('lineas[%d] no es valida.', $index));
            }

            $cantidad = $this->intObligatorio($lineaRaw, 'cantidad', sprintf('lineas[%d].', $index));
            if ($cantidad <= 0) {
                throw new \InvalidArgumentException(sprintf('lineas[%d].cantidad debe ser mayor que cero.', $index));
            }

            $precioAplicado = $this->floatObligatorio($lineaRaw, 'precioAplicado', sprintf('lineas[%d].', $index));
            if ($precioAplicado < 0) {
                throw new \InvalidArgumentException(sprintf('lineas[%d].precioAplicado no puede ser negativo.', $index));
            }

            $lineas[] = new VentaLineaInput(
                $this->intObligatorio($lineaRaw, 'productoId', sprintf('lineas[%d].', $index)),
                $cantidad,
                $precioAplicado,
                $this->floatOpcional($lineaRaw, 'precioOriginal'),
                $this->floatOpcional($lineaRaw, 'coste'),
                $this->floatOpcional($lineaRaw, 'descuento') ?? 0.0,
                $this->stringOpcional($lineaRaw, 'texto') ?? $this->stringOpcional($lineaRaw, 'descripcion'),
                $this->floatOpcional($lineaRaw, 'tipoIva')
            );
        }

        return new VentaTpvInput(
            $uuid,
            $fecha,
            $this->stringObligatorio($data, 'caja'),
            $this->normalizarFormaPago($this->stringObligatorio($data, 'formaPago')),
            $lineas,
            $this->stringOpcional($data, 'numeroTicketLocal'),
            $this->floatOpcional($data, 'total')
        );
    }

    private function normalizarFormaPago(string $formaPago): string
    {
        return match (strtoupper($formaPago)) {
            'EFECTIVO' => 'Efectivo',
            'TARJETA' => 'Tarjeta',
            default => $formaPago,
        };
    }

    private function autorizar(Request $request): array|bool|JsonResponse
    {
        $config = trim($this->tpvApiKeys);
        if ($config === '') {
            return $this->error('TPV_API_KEYS no esta configurado.', 503);
        }

        $apiKey = (string) $request->headers->get('X-TPV-API-Key', '');
        if ($apiKey === '') {
            return $this->error('Falta X-TPV-API-Key.', 401);
        }

        $cajaHeader = $request->headers->get('X-TPV-Caja');
        $json = json_decode($config, true);

        if (is_array($json)) {
            foreach ($json as $caja => $clave) {
                if ((string) $clave === $apiKey) {
                    return is_string($caja) ? ['caja' => $caja] : true;
                }
            }
        } else {
            foreach (array_map('trim', explode(',', $config)) as $clave) {
                if ($clave !== '' && hash_equals($clave, $apiKey)) {
                    return $cajaHeader ? ['caja' => $cajaHeader] : true;
                }
            }
        }

        return $this->error('API key no valida.', 401);
    }

    private function stringObligatorio(array $data, string $campo): string
    {
        $valor = $data[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === '') {
            throw new \InvalidArgumentException(sprintf('%s es obligatorio.', $campo));
        }

        return trim($valor);
    }

    private function stringOpcional(array $data, string $campo): ?string
    {
        $valor = $data[$campo] ?? null;
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    private function intObligatorio(array $data, string $campo, string $prefijo = ''): int
    {
        if (!isset($data[$campo]) || filter_var($data[$campo], FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException(sprintf('%s%s es obligatorio y debe ser entero.', $prefijo, $campo));
        }

        return (int) $data[$campo];
    }

    private function floatObligatorio(array $data, string $campo, string $prefijo = ''): float
    {
        if (!isset($data[$campo]) || !is_numeric($data[$campo])) {
            throw new \InvalidArgumentException(sprintf('%s%s es obligatorio y debe ser numerico.', $prefijo, $campo));
        }

        return (float) $data[$campo];
    }

    private function floatOpcional(array $data, string $campo): ?float
    {
        if (!isset($data[$campo]) || $data[$campo] === '') {
            return null;
        }

        if (!is_numeric($data[$campo])) {
            throw new \InvalidArgumentException(sprintf('%s debe ser numerico.', $campo));
        }

        return (float) $data[$campo];
    }

    private function error(string $mensaje, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => $mensaje,
        ], $status);
    }
}
