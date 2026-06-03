<?php

namespace App\Service\ProyectoGasto;

use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use App\Repository\DocumentoRepository;
use App\Repository\ProyectoGastoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;   
use App\Service\ForecastHandlerService;

class ProyectoGastoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ForecastHandlerService $forecastService
    ) {
    }

    public function confirmar(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
            throw new \LogicException('No se puede confirmar un gasto cancelado.');
        }

        if ($gasto->getEstado() === ProyectoGasto::ESTADO_PAGADO) {
            return;
        }

        $gasto->confirmar();

        if ($gasto->getImporteReal() === null) {
            $gasto->setImporteReal($gasto->getImportePrevisto());
        }

        $this->guardarCambios($gasto);
    }

    public function marcarPagado(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
            throw new \LogicException('No se puede pagar un gasto cancelado.');
        }

        if ($gasto->getImporteReal() === null) {
            $gasto->setImporteReal($gasto->getImportePrevisto());
        }

        $gasto->marcarPagado();

        $this->guardarCambios($gasto);
    }

    public function cancelar(ProyectoGasto $gasto): void
    {
        if ($gasto->getEstado() === ProyectoGasto::ESTADO_PAGADO) {
            throw new \LogicException('No se debería cancelar directamente un gasto pagado.');
        }

        $gasto->cancelar();

        $this->guardarCambios($gasto);
    }

    public function eliminar(ProyectoGasto $gasto): void
    {
        if (!$this->puedeEliminar($gasto)) {
            throw new \LogicException('Este gasto ya tiene trazabilidad y no se puede eliminar. Cancélalo.');
        }

        $proyecto = $gasto->getProyecto();

        $this->em->remove($gasto);
        $this->em->flush();

        if ($proyecto) {
            $this->recalcularProyecto($proyecto);
        }
    }

    public function puedeEliminar(ProyectoGasto $gasto): bool
    {
        return $gasto->getEstado() === ProyectoGasto::ESTADO_PREVISTO
            && $gasto->getForecast() === null
            && $gasto->getBancoMovimiento() === null
            && $gasto->getEfectivoMovimiento() === null;
    }

    private function guardarCambios(ProyectoGasto $gasto): void
    {
        $gasto->marcarActualizado();

        $this->sincronizarForecastSiProcede($gasto);

        if ($gasto->getProyecto()) {
            $this->recalcularProyecto($gasto->getProyecto());
        }

        $this->em->flush();
    }

    public function sincronizarForecastSiProcede(ProyectoGasto $gasto): void
    {
        if (!$gasto->isGeneraForecast()) {
            return;
        }

        $this->forecastService->sincronizarForecastSiProcede($gasto);
    }

    public function recalcularProyecto(Proyecto $proyecto): void
    {
        $totalPrevisto = 0.0;
        $totalConfirmado = 0.0;
        $totalPagado = 0.0;

        foreach ($proyecto->getGastos() as $gasto) {
            if ($gasto->getEstado() === ProyectoGasto::ESTADO_CANCELADO) {
                continue;
            }

            $totalPrevisto += (float) $gasto->getImportePrevisto();

            if (in_array($gasto->getEstado(), [
                ProyectoGasto::ESTADO_CONFIRMADO,
                ProyectoGasto::ESTADO_PAGADO,
            ], true)) {
                $totalConfirmado += (float) $gasto->getImporteEfectivo();
            }

            if ($gasto->getEstado() === ProyectoGasto::ESTADO_PAGADO) {
                $totalPagado += (float) $gasto->getImporteEfectivo();
            }
        }

        if (method_exists($proyecto, 'setActualizadoEn')) {
            $proyecto->setActualizadoEn(new \DateTime());
        }

        // Cuando añadas estos campos:
        // $proyecto->setTotalGastoPrevisto(number_format($totalPrevisto, 2, '.', ''));
        // $proyecto->setTotalGastoConfirmado(number_format($totalConfirmado, 2, '.', ''));
        // $proyecto->setTotalGastoPagado(number_format($totalPagado, 2, '.', ''));

        // OJO: aquí NO haría flush si este método lo llama guardarCambios()
        // para evitar flush duplicados.
    }
}