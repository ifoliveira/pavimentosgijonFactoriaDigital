<?php

namespace App\Repository;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebComunicacion;
use App\Enum\EstadoPresupuestoWebComunicacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresupuestoWebComunicacion>
 */
class PresupuestoWebComunicacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestoWebComunicacion::class);
    }

    /**
     * @return PresupuestoWebComunicacion[]
     */
    public function findPendientesParaPresupuesto(PresupuestoWeb $presupuesto): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.presupuestoWeb = :presupuesto')
            ->andWhere('c.estado = :estado')
            ->setParameter('presupuesto', $presupuesto)
            ->setParameter('estado', EstadoPresupuestoWebComunicacion::PENDIENTE->value)
            ->getQuery()
            ->getResult();
    }
}
