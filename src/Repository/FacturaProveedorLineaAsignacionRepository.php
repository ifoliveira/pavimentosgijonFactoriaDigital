<?php

namespace App\Repository;

use App\Entity\FacturaProveedorLineaAsignacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FacturaProveedorLineaAsignacion|null find($id, $lockMode = null, $lockVersion = null)
 * @method FacturaProveedorLineaAsignacion|null findOneBy(array $criteria, array $orderBy = null)
 * @method FacturaProveedorLineaAsignacion[]    findAll()
 * @method FacturaProveedorLineaAsignacion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FacturaProveedorLineaAsignacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FacturaProveedorLineaAsignacion::class);
    }

    // /**
    //  * @return Forecast[] Returns an array of Forecast objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Forecast
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
