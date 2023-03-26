<?php

namespace App\Repository;

use App\Entity\Cestas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Cestas|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cestas|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cestas[]    findAll()
 * @method Cestas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CestasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cestas::class);
    }

    public function ventaefemes()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_tot_cs), month(fecha_cs) as mes FROM cestas p
        WHERE tipopago_cs = "Efectivo"
        AND YEAR(fecha_cs) = YEAR(CURDATE())
        GROUP BY MONTH (fecha_cs);
            ';
        //$stmt = $conn->prepare($sql);
        //$stmt->execute();

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAllAssociative($sql);

    }


    public function ventaefetotal()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_tot_cs) as ventatotalef FROM cestas p
        WHERE tipopago_cs = "Efectivo";
            ';
        //$stmt = $conn->prepare($sql);
        //$stmt->execute();


        return $conn->fetchAssociative($sql);

    }


    /**
     * @return Cestas[]
     */
    public function ticketshoy()
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT p
            FROM App\Entity\Cestas p
            WHERE p.estadoCs < 5
              AND p.estadoCs > 1');

        // returns an array of Product objects
        return $query->getResult();

    }


    /**
     * @return Cestas[]
     */
    public function ticketssnal()
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT p
            FROM App\Entity\Cestas p
            WHERE p.estadoCs = 9');

        // returns an array of Product objects
        return $query->getResult();

    }

    // /**
    //  * @return Cestas[] Returns an array of Cestas objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Cestas
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
