<?php

namespace App\Controller;

use App\Entity\BolsaGastosAjuste;
use App\Repository\BolsaGastosAjusteRepository;
use App\Service\Tesoreria\BolsaGastosService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class BolsaGastosController extends AbstractController
{
    #[Route('/admin/flujocaja/bolsa', name: 'admin_bolsa_gastos', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        BolsaGastosService $bolsaService,
        BolsaGastosAjusteRepository $ajusteRepository,
        EntityManagerInterface $em,
    ): Response {
        $configuracion = $bolsaService->getConfiguracion();
        $hoy = new \DateTimeImmutable('today');
        $errores = [];
        $formConfiguracion = [
            'fechaInicio' => $configuracion->getFechaInicio()?->format('Y-m-d') ?? '',
            'ivaEstimado' => $configuracion->getIvaEstimado(),
            'plazoPagoDias' => (string) $configuracion->getPlazoPagoDias(),
            'plazoObraDias' => (string) $configuracion->getPlazoObraDias(),
            'proveedoresExcluidos' => implode("\n", $configuracion->getProveedoresExcluidos()),
        ];
        $formAjuste = ['fecha' => $hoy->format('Y-m-d'), 'importeBase' => '', 'motivo' => ''];

        if ($request->isMethod('POST')) {
            $datos = $request->request->all();
            $accion = $this->texto($datos, 'accion');

            if ($accion === 'guardar_configuracion') {
                if (!$this->isCsrfTokenValid('bolsa_gastos_configuracion', $this->texto($datos, '_token'))) {
                    throw $this->createAccessDeniedException('La sesión del formulario ha caducado. Vuelve a cargar la página.');
                }

                foreach (array_keys($formConfiguracion) as $campo) {
                    $formConfiguracion[$campo] = $this->texto($datos, $campo);
                    if (isset($datos[$campo]) && !is_string($datos[$campo])) {
                        $errores[] = 'El formulario de configuración contiene un valor no válido.';
                    }
                }

                $fechaTexto = $formConfiguracion['fechaInicio'];
                $fechaInicio = $fechaTexto === '' ? null : $this->fecha($fechaTexto);
                if ($fechaTexto !== '' && $fechaInicio === null) {
                    $errores[] = 'Indica una fecha de inicio válida o deja el campo vacío para desactivar la bolsa.';
                }

                $iva = $this->decimal($formConfiguracion['ivaEstimado'], 10000);
                if ($iva === null) {
                    $errores[] = 'El IVA estimado debe estar entre 0 y 100, con un máximo de dos decimales.';
                }

                $plazoPago = $this->entero($formConfiguracion['plazoPagoDias'], 365);
                if ($plazoPago === null) {
                    $errores[] = 'El plazo de pago debe ser un número entero entre 0 y 365 días.';
                }

                $plazoObra = $this->entero($formConfiguracion['plazoObraDias'], 730);
                if ($plazoObra === null) {
                    $errores[] = 'El plazo de obra debe ser un número entero entre 0 y 730 días.';
                }

                $proveedores = array_values(array_unique(array_filter(
                    array_map('trim', preg_split('/\R/u', $formConfiguracion['proveedoresExcluidos']) ?: []),
                    static fn (string $proveedor): bool => $proveedor !== '',
                )));
                foreach ($proveedores as $proveedor) {
                    if (mb_strlen($proveedor) > 255) {
                        $errores[] = 'Cada nombre de proveedor debe tener como máximo 255 caracteres.';
                    }
                }

                if ($errores === []) {
                    $configuracion->setFechaInicio($fechaInicio);
                    $configuracion->setIvaEstimado($iva);
                    $configuracion->setPlazoPagoDias($plazoPago);
                    $configuracion->setPlazoObraDias($plazoObra);
                    $configuracion->setProveedoresExcluidos($proveedores);
                    $em->persist($configuracion);
                    $em->flush();

                    $this->addFlash('success', $fechaInicio === null
                        ? 'Configuración guardada. La bolsa está desactivada y sus datos se conservan.'
                        : 'Configuración de la bolsa guardada.');

                    return $this->redirectToRoute('admin_bolsa_gastos');
                }
            } elseif ($accion === 'crear_ajuste') {
                if (!$this->isCsrfTokenValid('bolsa_gastos_ajuste', $this->texto($datos, '_token'))) {
                    throw $this->createAccessDeniedException('La sesión del formulario ha caducado. Vuelve a cargar la página.');
                }

                foreach (array_keys($formAjuste) as $campo) {
                    $formAjuste[$campo] = $this->texto($datos, $campo);
                }

                $fechaInicio = $configuracion->getFechaInicio();
                $fechaAjuste = $this->fecha($formAjuste['fecha']);
                if ($fechaInicio === null) {
                    $errores[] = 'Elige y guarda una fecha de inicio antes de añadir ajustes.';
                }
                if ($fechaAjuste === null) {
                    $errores[] = 'Indica una fecha válida para el ajuste.';
                } elseif ($fechaAjuste > $hoy || ($fechaInicio !== null && $fechaAjuste < $fechaInicio)) {
                    $errores[] = 'La fecha del ajuste debe estar entre el inicio de la bolsa y hoy.';
                }

                $importe = $this->decimal($formAjuste['importeBase'], 999999999999, true);
                if ($importe === null || $importe === '0.00') {
                    $errores[] = 'Indica un importe distinto de cero, con signo positivo o negativo y un máximo de dos decimales, sin separadores de miles.';
                }

                $motivo = $formAjuste['motivo'];
                if ($motivo === '') {
                    $errores[] = 'Indica el motivo del ajuste.';
                } elseif (mb_strlen($motivo) > 255) {
                    $errores[] = 'El motivo del ajuste no puede superar los 255 caracteres.';
                }

                if ($errores === []) {
                    $ajuste = new BolsaGastosAjuste();
                    $ajuste->setFecha($fechaAjuste);
                    $ajuste->setImporteBase($importe);
                    $ajuste->setMotivo($motivo);
                    $em->persist($ajuste);
                    $em->flush();

                    $this->addFlash('success', 'Ajuste añadido a la bolsa.');

                    return $this->redirectToRoute('admin_bolsa_gastos');
                }
            } else {
                $errores[] = 'La acción solicitada no es válida.';
            }
        }

        return $this->render('admin_pro/bolsa_gastos.html.twig', [
            'resumen' => $bolsaService->getResumen(),
            'configuracion' => $configuracion,
            'formConfiguracion' => $formConfiguracion,
            'formAjuste' => $formAjuste,
            'errores' => array_values(array_unique($errores)),
            'hoy' => $hoy,
            'ajustes' => $configuracion->getFechaInicio() !== null
                ? $ajusteRepository->findHasta($configuracion->getFechaInicio(), $hoy)
                : [],
        ], new Response(status: $errores === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /** @param array<string, mixed> $datos */
    private function texto(array $datos, string $campo): string
    {
        return isset($datos[$campo]) && is_string($datos[$campo]) ? trim($datos[$campo]) : '';
    }

    private function fecha(string $valor): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor)) {
            return null;
        }

        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor && (int) $fecha->format('Y') > 0
            ? $fecha
            : null;
    }

    private function entero(string $valor, int $maximo): ?int
    {
        if (!preg_match('/^\d{1,3}$/D', $valor)) {
            return null;
        }

        $numero = (int) $valor;

        return $numero <= $maximo ? $numero : null;
    }

    private function decimal(string $valor, int $maximoCentimos, bool $permiteNegativo = false): ?string
    {
        $patron = $permiteNegativo ? '/^[+-]?\d{1,10}(?:[.,]\d{1,2})?$/D' : '/^\d{1,10}(?:[.,]\d{1,2})?$/D';
        if (!preg_match($patron, $valor)) {
            return null;
        }

        $negativo = str_starts_with($valor, '-');
        $partes = explode('.', str_replace(',', '.', ltrim($valor, '+-')));
        $centimos = (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
        if ($centimos > $maximoCentimos) {
            return null;
        }

        return sprintf('%s%d.%02d', $negativo && $centimos > 0 ? '-' : '', intdiv($centimos, 100), $centimos % 100);
    }
}
