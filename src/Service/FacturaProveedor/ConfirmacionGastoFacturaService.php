<?php

namespace App\Service\FacturaProveedor;

use App\Entity\FacturaProveedor;
use App\Entity\FacturaProveedorLinea;
use App\Entity\ProyectoGasto;
use App\Repository\ProyectoGastoRepository;
use App\Service\Proyecto\ProyectoCalculatorService;
use App\Service\ProyectoGasto\ProyectoGastoService;
use Doctrine\ORM\EntityManagerInterface;

/** Confirma costes reales desde una factura sin crear asociaciones ni forecast. */
final class ConfirmacionGastoFacturaService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProyectoGastoRepository $gastos,
        private readonly ProyectoCalculatorService $proyectoCalculator,
        private readonly ProyectoGastoService $proyectoGastoService,
        private readonly BuscadorGastoPrevistoService $buscador,
    ) {
    }

    /** @param array<int, int|string> $selecciones */
    public function confirmarSeleccionadas(FacturaProveedor $factura, array $selecciones): int
    {
        $this->em->beginTransaction();

        try {
            $confirmadas = 0;
            $proyectos = [];

            foreach ($selecciones as $lineaId => $gastoId) {
                $linea = $this->buscarLinea($factura, (int) $lineaId);
                $gasto = $this->gastos->find((int) $gastoId);

                if (!$linea || !$gasto) {
                    throw new \LogicException('La línea o el gasto seleccionado ya no existe.');
                }

                $esPropuestaVigente = false;
                foreach ($this->buscador->buscar($linea) as $candidato) {
                    if ($candidato['gasto']->getId() === $gasto->getId()) {
                        $esPropuestaVigente = true;
                        break;
                    }
                }
                if (!$esPropuestaVigente) {
                    throw new \LogicException('El gasto seleccionado no es una propuesta válida para esta línea.');
                }

                $this->confirmar($linea, $gasto);
                $proyectos[$gasto->getProyecto()->getId()] = $gasto->getProyecto();
                ++$confirmadas;
            }

            foreach ($proyectos as $proyecto) {
                $this->proyectoGastoService->recalcularProyecto($proyecto);
                $this->proyectoCalculator->recalcularProyecto($proyecto, false);
            }

            $this->em->flush();
            $this->em->commit();

            return $confirmadas;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    private function confirmar(FacturaProveedorLinea $linea, ProyectoGasto $gasto): void
    {
        if ($linea->getEstado() !== 'pendiente') {
            throw new \LogicException('La línea de factura ya fue resuelta o asignada.');
        }
        if ($gasto->getEstado() !== ProyectoGasto::ESTADO_PREVISTO || $gasto->getProyecto()?->getFechaFinReal() !== null) {
            throw new \LogicException('El gasto seleccionado ya no está disponible para confirmar.');
        }

        $base = $linea->getImporteBruto() ?? $linea->getBase();
        if ($base === null || (float) $base <= 0) {
            throw new \LogicException('La línea no tiene una base imponible fiable.');
        }

        // El coste del proyecto siempre es la base, nunca el total con IVA.
        $gasto->setBaseReal(number_format((float) $base, 2, '.', ''));
        $gasto->setImporteReal(number_format((float) $base, 2, '.', ''));
        $gasto->setTipoIvaReal($linea->getPorcentajeIva() === null ? null : number_format($linea->getPorcentajeIva(), 2, '.', ''));
        $gasto->setIvaReal(number_format((float) ($linea->getImporteIva() ?? 0), 2, '.', ''));
        $gasto->setRecargoReal(number_format((float) ($linea->getImporteRecargoEquivalencia() ?? 0), 2, '.', ''));
        $gasto->confirmar();

        // Evita reutilizar la línea sin crear una relación factura-gasto persistente.
        $linea->setEstado('confirmada_gasto');
    }

    private function buscarLinea(FacturaProveedor $factura, int $lineaId): ?FacturaProveedorLinea
    {
        foreach ($factura->getLineas() as $linea) {
            if ($linea->getId() === $lineaId) {
                return $linea;
            }
        }

        return null;
    }
}
