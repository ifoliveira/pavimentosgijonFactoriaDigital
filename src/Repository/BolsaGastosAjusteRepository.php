<?php

namespace App\Repository;

use App\Entity\BolsaGastosAjuste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BolsaGastosAjuste> */
class BolsaGastosAjusteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BolsaGastosAjuste::class);
    }

    /** @return BolsaGastosAjuste[] */
    public function findHasta(\DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.fecha >= :desde')
            ->andWhere('a.fecha <= :hasta')
            ->setParameter('desde', $desde->format('Y-m-d'))
            ->setParameter('hasta', $hasta->format('Y-m-d'))
            ->orderBy('a.fecha', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
