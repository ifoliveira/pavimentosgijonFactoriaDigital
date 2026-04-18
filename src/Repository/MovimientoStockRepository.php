<?php

namespace App\Repository;

use App\Entity\MovimientoStock;
use App\Entity\Productos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MovimientoStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovimientoStock::class);
    }

    public function findUltimoMovimientoProducto(Productos $producto): ?MovimientoStock
    {
        return $this->createQueryBuilder('m')
            ->where('m.producto = :producto')
            ->setParameter('producto', $producto)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getStockActual(Productos $producto): string
    {
        $result = $this->createQueryBuilder('m')
            ->select('SUM(
                CASE 
                    WHEN m.tipo = \'entrada\' THEN m.cantidad
                    WHEN m.tipo = \'devolucion\' THEN m.cantidad
                    WHEN m.tipo = \'salida\' THEN -m.cantidad
                    WHEN m.tipo = \'ajuste\' AND m.ajusteNegativo = false THEN m.cantidad
                    WHEN m.tipo = \'ajuste\' AND m.ajusteNegativo = true THEN -m.cantidad
                END
            ) as stock')
            ->where('m.producto = :producto')
            ->setParameter('producto', $producto)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.000';
    }
}