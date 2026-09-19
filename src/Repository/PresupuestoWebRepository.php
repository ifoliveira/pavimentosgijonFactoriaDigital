<?php

namespace App\Repository;

use App\Entity\PresupuestoWeb;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresupuestoWeb>
 */
class PresupuestoWebRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestoWeb::class);
    }
}
