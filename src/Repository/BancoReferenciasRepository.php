<?php

namespace App\Repository;

use App\Entity\BancoReferencias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BancoReferencias|null find($id, $lockMode = null, $lockVersion = null)
 * @method BancoReferencias|null findOneBy(array $criteria, array $orderBy = null)
 * @method BancoReferencias[]    findAll()
 * @method BancoReferencias[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BancoReferenciasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BancoReferencias::class);
    }

    // /**
    //  * @return BancoReferencias[] Returns an array of BancoReferencias objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BancoReferencias
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
