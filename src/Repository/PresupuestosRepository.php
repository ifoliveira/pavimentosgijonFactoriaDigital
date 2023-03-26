<?php

namespace App\Repository;

use App\Entity\Presupuestos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Presupuestos|null find($id, $lockMode = null, $lockVersion = null)
 * @method Presupuestos|null findOneBy(array $criteria, array $orderBy = null)
 * @method Presupuestos[]    findAll()
 * @method Presupuestos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PresupuestosRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Presupuestos::class);
    }

    public function numeroestado()
    {

        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT estado_pe_id as estado, count(*) as cantidad FROM presupuestos 
                 GROUP BY estado_pe_id';
        //$stmt = $conn->prepare($sql);
        //$stmt->execute();
        return $conn->fetchAllAssociative($sql);



        return $this->createQueryBuilder('pe')
            ->select('pe.estado_pe_id, COUNT(pe.estado_pe_id) as TotalDetalle')
            ->groupBy('pe.estado_pe_id')
            ->orderBy('pe.estado_pe_id')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // /**
    //  * @return Presupuestos[] Returns an array of Presupuestos objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Presupuestos
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
