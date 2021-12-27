<?php

namespace App\Repository;

use App\Entity\ManoObra;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ManoObra|null find($id, $lockMode = null, $lockVersion = null)
 * @method ManoObra|null findOneBy(array $criteria, array $orderBy = null)
 * @method ManoObra[]    findAll()
 * @method ManoObra[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ManoObraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManoObra::class);
    }

    // /**
    //  * @return ManoObra[] Returns an array of ManoObra objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ManoObra
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
