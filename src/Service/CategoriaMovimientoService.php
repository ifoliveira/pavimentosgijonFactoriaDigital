<?php
namespace App\Service;

use App\Entity\Tiposmovimiento;
use App\Repository\TiposmovimientoRepository;

class CategoriaMovimientoService
{
    public function __construct(
        private TiposmovimientoRepository $tiposMovimientoRepository,
    ) {}

    public function getCategoriaPorConcepto(string $concepto): ?Tiposmovimiento
    {
        foreach ($this->tiposMovimientoRepository->findAll() as $tipo) {
            if ($tipo->getPatronBusqueda() && preg_match($tipo->getPatronBusqueda(), $concepto)) {
                return $tipo;
            }
        }

        return null;
    }
}