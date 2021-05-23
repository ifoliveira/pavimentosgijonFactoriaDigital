<?php

namespace App\Repository;

use App\Entity\Tipoproducto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Tipoproducto|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tipoproducto|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tipoproducto[]    findAll()
 * @method Tipoproducto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TipoproductoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tipoproducto::class);
    }

    // /**
    //  * @return Tipoproducto[] Returns an array of Tipoproducto objects
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
    public function findOneBySomeField($value): ?Tipoproducto
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
