<?php

namespace App\Repository;

use App\Entity\Estadocestas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Estadocestas|null find($id, $lockMode = null, $lockVersion = null)
 * @method Estadocestas|null findOneBy(array $criteria, array $orderBy = null)
 * @method Estadocestas[]    findAll()
 * @method Estadocestas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstadocestasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estadocestas::class);
    }

    // /**
    //  * @return Estadocestas[] Returns an array of Estadocestas objects
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
    public function findOneBySomeField($value): ?Estadocestas
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
