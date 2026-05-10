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