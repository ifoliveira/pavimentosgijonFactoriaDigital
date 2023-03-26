<?php

namespace App\Repository;

use App\Entity\Efectivo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Efectivo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Efectivo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Efectivo[]    findAll()
 * @method Efectivo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EfectivoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Efectivo::class);
    }


    public function totalefectivo()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT sum(importe_ef) as efectivototal  FROM efectivo p
            ';
        //$stmt = $conn->prepare($sql);
        //$stmt->execute();

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }

    public function manoobraEfectivo()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_ef)   FROM efectivo p
        WHERE tipoEf = 1
          AND YEAR(fecha_ef) = YEAR(CURDATE());
            ';
        //$stmt = $conn->prepare($sql);
        //$stmt->execute();

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }

    // /**
    //  * @return Efectivo[] Returns an array of Efectivo objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Efectivo
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
