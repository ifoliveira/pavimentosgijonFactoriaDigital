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
            ->select('SUM (dc.cantidadDc * (dc.pvpDc - (dc.pvpDc * dc.descuentoDc /100))) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function descuentoCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc * (dc.pvpDc * dc.descuentoDc /100)) as TotalDescuento')
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

    public function beneficioTotal()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(pvp_dc * cantidad_dc) as pvp, sum(precio_dc * cantidad_dc) as precio FROM detallecesta a inner Join cestas p
        ON cesta_dc_id = p.id
        WHERE YEAR(timestamp_dc) = YEAR(CURDATE())
          AND precio_dc <> 0
          AND estado_cs <> 11;
            ';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

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
