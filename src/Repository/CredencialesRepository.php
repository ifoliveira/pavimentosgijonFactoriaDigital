<?php

namespace App\Repository;

use App\Entity\Credenciales;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Credenciales|null find($id, $lockMode = null, $lockVersion = null)
 * @method Credenciales|null findOneBy(array $criteria, array $orderBy = null)
 * @method Credenciales[]    findAll()
 * @method Credenciales[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CredencialesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credenciales::class);
    }

    // /**
    //  * @return Credenciales[] Returns an array of Credenciales objects
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
    public function findOneBySomeField($value): ?Credenciales
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
