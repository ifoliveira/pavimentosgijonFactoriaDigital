<?php

namespace App\Repository;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebInteraccion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresupuestoWebInteraccion>
 */
class PresupuestoWebInteraccionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestoWebInteraccion::class);
    }

    /**
     * @return PresupuestoWebInteraccion[]
     */
    public function findByPresupuestoOrdenadas(PresupuestoWeb $presupuesto): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.comunicacionOrigen', 'c')->addSelect('c')
            ->andWhere('i.presupuestoWeb = :presupuesto')
            ->setParameter('presupuesto', $presupuesto)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUltimaParaPresupuesto(PresupuestoWeb $presupuesto): ?PresupuestoWebInteraccion
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.comunicacionOrigen', 'c')->addSelect('c')
            ->andWhere('i.presupuestoWeb = :presupuesto')
            ->setParameter('presupuesto', $presupuesto)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
