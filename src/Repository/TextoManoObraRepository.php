<?php

namespace App\Repository;

use App\Entity\TextoManoObra;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TextoManoObra|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextoManoObra|null findOneBy(array $criteria, array $orderBy = null)
 * @method TextoManoObra[]    findAll()
 * @method TextoManoObra[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TextoManoObraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TextoManoObra::class);
    }

    /**
      * @return TextoManoObra[] Returns an array of TextoManoObra objects
    */
    
    public function findByActivos()
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.activo = :val')
            ->setParameter('val', true)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?TextoManoObra
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
