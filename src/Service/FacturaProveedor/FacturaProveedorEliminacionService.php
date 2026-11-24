<?php

namespace App\Service\FacturaProveedor;

use App\Entity\FacturaProveedor;
use App\Entity\StockMovimiento;
use App\Service\ProyectoGasto\ProyectoGastoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Elimina una factura importada y todos los datos que nacieron de ella. */
final class FacturaProveedorEliminacionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProyectoGastoService $proyectoGastoService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function eliminar(FacturaProveedor $factura): void
    {
        $rutaPdf = $this->rutaPdfSegura($factura->getRutaPdf());
        $proyectos = [];
        $gastosEliminados = [];

        $this->em->beginTransaction();

        try {
            foreach ($factura->getLineas() as $linea) {
                foreach ($linea->getAsignaciones() as $asignacion) {
                    foreach ($this->em->getRepository(StockMovimiento::class)->findBy([
                        'facturaProveedorLineaAsignacion' => $asignacion,
                    ]) as $movimientoStock) {
                        $this->em->remove($movimientoStock);
                    }

                    $gasto = $asignacion->getProyectoGasto();
                    if ($gasto !== null && !isset($gastosEliminados[$gasto->getId()])) {
                        $proyecto = $gasto->getProyecto();
                        if ($proyecto !== null) {
                            $proyectos[$proyecto->getId()] = $proyecto;
                            $proyecto->removeGasto($gasto);
                        }

                        // El forecast de la factura se elimina con ella. Rompemos
                        // antes la relación desde el gasto para respetar las FK.
                        $gasto->setForecast(null);
                        $this->em->remove($gasto);
                        $gastosEliminados[$gasto->getId()] = true;
                    }

                    $this->em->remove($asignacion);
                }

                $this->em->remove($linea);
            }

            foreach ($factura->getForecasts()->toArray() as $forecast) {
                $this->em->remove($forecast);
            }

            $this->em->remove($factura);

            foreach ($proyectos as $proyecto) {
                $this->proyectoGastoService->recalcularProyecto($proyecto);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        // El fichero se borra únicamente después de confirmar la transacción.
        if ($rutaPdf !== null && is_file($rutaPdf)) {
            @unlink($rutaPdf);
        }
    }

    private function rutaPdfSegura(?string $rutaRelativa): ?string
    {
        if ($rutaRelativa === null || $rutaRelativa === '') {
            return null;
        }

        $directorio = realpath($this->projectDir.'/var/facturas-proveedor');
        $archivo = realpath($this->projectDir.'/'.ltrim($rutaRelativa, '/'));

        if ($directorio === false || $archivo === false || !str_starts_with($archivo, $directorio.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $archivo;
    }
}
