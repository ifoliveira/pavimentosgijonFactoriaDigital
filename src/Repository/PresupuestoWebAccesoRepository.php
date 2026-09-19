<?php

namespace App\Repository;

use App\Entity\PresupuestoWebAcceso;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresupuestoWebAcceso>
 */
class PresupuestoWebAccesoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestoWebAcceso::class);
    }
}
