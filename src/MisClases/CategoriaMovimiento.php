<?php

namespace App\MisClases;


use App\Entity\Tiposmovimiento;
use App\Repository\TiposmovimientoRepository;
use App\Entity\Banco;
use App\Repository\BancoRepository;

class CategoriaMovimiento
{
    private $tiposMovimientoRepository;

    public function __construct(TiposmovimientoRepository $tiposMovimientoRepository, BancoRepository $bancoRepository)
    {
        $this->tiposMovimientoRepository = $tiposMovimientoRepository;

    }

    public function getCategoriaPorConcepto(string $concepto): ?Tiposmovimiento
    {

        $tiposMovimiento = $this->tiposMovimientoRepository->findAll();

        foreach ($tiposMovimiento as $tipo) {
            if ($tipo->getPatronBusqueda() && preg_match($tipo->getPatronBusqueda(), $concepto)) {
                return $tipo;
            }
        }

        return null; // Devuelve null si ninguna regla coincide
    }
}