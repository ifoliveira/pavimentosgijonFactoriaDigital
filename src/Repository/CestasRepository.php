<?php
namespace App\Repository;

use App\Entity\Cestas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CestasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cestas::class);
    }

    /**
     * Tickets finalizados (estadoCs = 2) — usados en vista diaria
     */
    public function findFinalizados(): array
    {
        return $this->getEntityManager()
            ->createQuery('SELECT c FROM App\Entity\Cestas c WHERE c.estadoCs = 2')
            ->getResult();
    }

    /**
     * Tickets pendientes de cobro (estadoCs = 3) — semana en curso
     */
    public function findPendientesCobro(): array
    {
        return $this->getEntityManager()
            ->createQuery('SELECT c FROM App\Entity\Cestas c WHERE c.estadoCs = 3')
            ->getResult();
    }

}