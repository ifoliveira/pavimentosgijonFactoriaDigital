<?php

namespace App\Repository;

use App\Entity\PresupuestoWebLead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresupuestoWebLead>
 */
class PresupuestoWebLeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestoWebLead::class);
    }
}
