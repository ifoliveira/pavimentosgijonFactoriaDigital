<?php

namespace App\Repository;

use App\Entity\StockReserva;
use App\Entity\Productos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockReserva>
 *
 * @method StockReserva|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockReserva|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockReserva[]    findAll()
 * @method StockReserva[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockReservaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockReserva::class);
    }

    public function getCantidadReservadaPorProducto(Productos $producto): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.cantidad), 0)')
            ->where('r.producto = :producto')
            ->andWhere('r.estado = :estado')
            ->setParameter('producto', $producto)
            ->setParameter('estado', StockReserva::ESTADO_RESERVADA)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }    

//    /**
//     * @return StockReserva[] Returns an array of StockReserva objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?StockReserva
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
