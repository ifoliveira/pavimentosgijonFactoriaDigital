<?php

namespace App\Repository;

use App\Entity\PresupuestosLead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresupuestosLead>
 *
 * @method PresupuestosLead|null find($id, $lockMode = null, $lockVersion = null)
 * @method PresupuestosLead|null findOneBy(array $criteria, array $orderBy = null)
 * @method PresupuestosLead[]    findAll()
 * @method PresupuestosLead[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PresupuestosLeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresupuestosLead::class);
    }

    public function add(PresupuestosLead $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PresupuestosLead $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PresupuestosLead[] Returns an array of PresupuestosLead objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PresupuestosLead
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
