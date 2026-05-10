<?php

namespace App\Service;

use App\Entity\CatalogoProducto;
use App\Entity\CatalogoProductoConfiguracion;
use App\Repository\CatalogoProductoConfiguracionRepository;

class CatalogoProductoSelectorService
{
    public function __construct(
        private CatalogoProductoConfiguracionRepository $configuracionRepository
    ) {}

    /**
     * Busca el producto recomendado compatible con el configurador.
     *
     * Ejemplo:
     * - configuradorCodigo: ducha
     * - uso: plato
     * - tipo: resina
     * - largo: 170
     * - ancho: 70
     */
    public function buscarRecomendado(
        string $configuradorCodigo,
        string $uso,
        ?string $tipo = null,
        ?float $largo = null,
        ?float $ancho = null,
        ?float $alto = null
    ): ?CatalogoProductoConfiguracion {
        $qb = $this->configuracionRepository->createQueryBuilder('c')
            ->join('c.producto', 'p')
            ->addSelect('p')
            ->where('c.configuradorCodigo = :configuradorCodigo')
            ->andWhere('c.uso = :uso')
            ->andWhere('c.activo = true')
            ->andWhere('p.activo = true')
            ->andWhere('p.visiblePresupuesto = true')
            ->setParameter('configuradorCodigo', $configuradorCodigo)
            ->setParameter('uso', $uso);

        if ($tipo !== null && $tipo !== '') {
            $qb
                ->andWhere('(c.tipo = :tipo OR c.tipo IS NULL)')
                ->setParameter('tipo', $tipo);
        }

        if ($largo !== null) {
            $qb
                ->andWhere('(c.largoMin IS NULL OR c.largoMin <= :largo)')
                ->andWhere('(c.largoMax IS NULL OR c.largoMax >= :largo)')
                ->setParameter('largo', $largo);
        }

        if ($ancho !== null) {
            $qb
                ->andWhere('(c.anchoMin IS NULL OR c.anchoMin <= :ancho)')
                ->andWhere('(c.anchoMax IS NULL OR c.anchoMax >= :ancho)')
                ->setParameter('ancho', $ancho);
        }

        if ($alto !== null) {
            $qb
                ->andWhere('(c.altoMin IS NULL OR c.altoMin <= :alto)')
                ->andWhere('(c.altoMax IS NULL OR c.altoMax >= :alto)')
                ->setParameter('alto', $alto);
        }

        $qb
            ->orderBy('c.recomendado', 'DESC')
            ->addOrderBy('c.prioridad', 'ASC')
            ->addOrderBy('p.precioVenta', 'ASC');

        return $qb
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function buscarProductoRecomendado(
        string $configuradorCodigo,
        string $uso,
        ?string $tipo = null,
        ?float $largo = null,
        ?float $ancho = null,
        ?float $alto = null
    ): ?CatalogoProducto {
        $configuracion = $this->buscarRecomendado(
            configuradorCodigo: $configuradorCodigo,
            uso: $uso,
            tipo: $tipo,
            largo: $largo,
            ancho: $ancho,
            alto: $alto
        );

        return $configuracion?->getProducto();
    }

    /**
     * Devuelve varias opciones compatibles.
     * Esto luego te servirá para mostrar alternativas en pantalla.
     */
    public function buscarOpciones(
        string $configuradorCodigo,
        string $uso,
        ?string $tipo = null,
        ?float $largo = null,
        ?float $ancho = null,
        ?float $alto = null,
        int $limite = 10
    ): array {
        $qb = $this->configuracionRepository->createQueryBuilder('c')
            ->join('c.producto', 'p')
            ->addSelect('p')
            ->where('c.configuradorCodigo = :configuradorCodigo')
            ->andWhere('c.uso = :uso')
            ->andWhere('c.activo = true')
            ->andWhere('p.activo = true')
            ->andWhere('p.visiblePresupuesto = true')
            ->setParameter('configuradorCodigo', $configuradorCodigo)
            ->setParameter('uso', $uso);

        if ($tipo !== null && $tipo !== '') {
            $qb
                ->andWhere('(c.tipo = :tipo OR c.tipo IS NULL)')
                ->setParameter('tipo', $tipo);
        }

        if ($largo !== null) {
            $qb
                ->andWhere('(c.largoMin IS NULL OR c.largoMin <= :largo)')
                ->andWhere('(c.largoMax IS NULL OR c.largoMax >= :largo)')
                ->setParameter('largo', $largo);
        }

        if ($ancho !== null) {
            $qb
                ->andWhere('(c.anchoMin IS NULL OR c.anchoMin <= :ancho)')
                ->andWhere('(c.anchoMax IS NULL OR c.anchoMax >= :ancho)')
                ->setParameter('ancho', $ancho);
        }

        if ($alto !== null) {
            $qb
                ->andWhere('(c.altoMin IS NULL OR c.altoMin <= :alto)')
                ->andWhere('(c.altoMax IS NULL OR c.altoMax >= :alto)')
                ->setParameter('alto', $alto);
        }

        return $qb
            ->orderBy('c.recomendado', 'DESC')
            ->addOrderBy('c.prioridad', 'ASC')
            ->addOrderBy('p.precioVenta', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}