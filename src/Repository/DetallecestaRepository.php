<?php

namespace App\Repository;

use App\Entity\Detallecesta;
use App\Entity\Cestas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Detallecesta|null find($id, $lockMode = null, $lockVersion = null)
 * @method Detallecesta|null findOneBy(array $criteria, array $orderBy = null)
 * @method Detallecesta[]    findAll()
 * @method Detallecesta[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DetallecestaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detallecesta::class);
    }

    public function imptotalCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc * dc.pvpDc) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function cantotalCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }
    

    public function preciototalCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc * dc.precioDc) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // /**
    //  * @return Detallecesta[] Returns an array of Detallecesta objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Detallecesta
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
