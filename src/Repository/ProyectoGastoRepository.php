<?php

namespace App\Repository;

use App\Entity\Proyecto;
use App\Entity\ProyectoGasto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProyectoGastoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProyectoGasto::class);
    }

    public function sumarCosteActualPorProyecto(Proyecto $proyecto): float
    {
        return (float) $this->createQueryBuilder('g')
            ->select('COALESCE(SUM(COALESCE(g.importeReal, g.importePrevisto)), 0)')
            ->andWhere('g.proyecto = :proyecto')
            ->andWhere('g.estado != :cancelado')
            ->setParameter('proyecto', $proyecto)
            ->setParameter('cancelado', ProyectoGasto::ESTADO_CANCELADO)
            ->getQuery()
            ->getSingleScalarResult();
    }
        
    public function sumarImportePorProyecto(Proyecto $proyecto): float
    {
        $total = $this->createQueryBuilder('g')
            ->select('COALESCE(SUM(g.importePrevisto), 0)')
            ->andWhere('g.proyecto = :proyecto')
            ->setParameter('proyecto', $proyecto)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $total;
    }
}