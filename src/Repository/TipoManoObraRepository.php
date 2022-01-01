<?php

namespace App\Repository;

use App\Entity\TipoManoObra;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TipoManoObra|null find($id, $lockMode = null, $lockVersion = null)
 * @method TipoManoObra|null findOneBy(array $criteria, array $orderBy = null)
 * @method TipoManoObra[]    findAll()
 * @method TipoManoObra[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TipoManoObraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TipoManoObra::class);
    }

    // /**
    //  * @return TipoManoObra[] Returns an array of TipoManoObra objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?TipoManoObra
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
