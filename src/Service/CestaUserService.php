<?php
namespace App\Service;

use App\Entity\Cestas;
use App\Entity\Detallecesta;
use App\Repository\CestasRepository;
use App\Repository\DetallecestaRepository;

class CestaUserService
{
    public function __construct(
        private CestasRepository $cestasRepository,
        private DetallecestaRepository $detallecestaRepository,
    ) {}

    public function getCesta(int $cestaId): ?Cestas
    {
        return $this->cestasRepository->find($cestaId);
    }

    public function getCestaArray(int $cestaId): ?array
    {
        $cesta    = $this->getCesta($cestaId);
        $detalles = $cesta?->getDetallecesta();

        if (!$detalles || $detalles->isEmpty()) {
            return null;
        }

        return array_map(fn($d) => [
            'cantidad'    => $d->getCantidadDc(),
            'descripcion' => $d->getProductoDc()->getDescripcionPd(),
            'precio'      => $d->getPvpDc(),
        ], $detalles->toArray());
    }

    public function getImporteTot(int $cestaId): float
    {
        $cesta = $this->getCesta($cestaId);
        $item  = $cesta ? $this->detallecestaRepository->imptotalCesta($cesta) : null;

        return $item ? round($item, 2) : 0.0;
    }

    public function getDescuentoTot(int $cestaId): ?float
    {
        $cesta = $this->getCesta($cestaId);
        $item  = $cesta ? $this->detallecestaRepository->descuentoCesta($cesta) : null;

        return $item ? round($item, 2) : null;
    }

    public function getCantidadTot(int $cestaId): int
    {
        $cesta = $this->getCesta($cestaId);
        $item  = $cesta ? $this->detallecestaRepository->cantotalCesta($cesta) : null;

        return $item ?? 0;
    }

    public function getPrecioTot(int $cestaId): float
    {
        $cesta = $this->getCesta($cestaId);
        $item  = $cesta ? $this->detallecestaRepository->preciototalCesta($cesta) : null;

        return $item ?? 0.0;
    }
}