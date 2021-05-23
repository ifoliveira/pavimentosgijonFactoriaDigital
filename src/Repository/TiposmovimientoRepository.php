<?php

namespace App\Repository;

use App\Entity\Tiposmovimiento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Tiposmovimiento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tiposmovimiento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tiposmovimiento[]    findAll()
 * @method Tiposmovimiento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TiposmovimientoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tiposmovimiento::class);
    }

    // /**
    //  * @return Tiposmovimiento[] Returns an array of Tiposmovimiento objects
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
    public function findOneBySomeField($value): ?Tiposmovimiento
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
