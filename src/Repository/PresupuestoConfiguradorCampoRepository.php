<?php

namespace App\Repository;

use App\Entity\PresupuestoConfiguradorCampo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PresupuestoConfiguradorCampoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestoConfiguradorCampo::class);
    }
}