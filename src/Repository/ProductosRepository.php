<?php

namespace App\Repository;
use App\Entity\Tipoproducto;
use App\Entity\Productos;
use App\Entity\StockMovimiento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Productos|null find($id, $lockMode = null, $lockVersion = null)
 * @method Productos|null findOneBy(array $criteria, array $orderBy = null)
 * @method Productos[]    findAll()
 * @method Productos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductosRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Productos::class);
    }

    /**
     * @return Productos[]
     */
    public function findAllgenericos(): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT p
            FROM App\Entity\Productos p
            WHERE p.id > 9999'
        );

        // returns an array of Product objects
        return $query->getResult();
    }

    /**
     * @return Productos[]
     */
    public function findBySearchQuery(string $q, int $limit = 12): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tipo_pd_id', 't')
            ->addSelect('t')
            ->where('p.descripcion_Pd LIKE :q')
            ->andWhere('p.obsoleto = false OR p.obsoleto IS NULL')
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('p.descripcion_Pd', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBySearchQueryConStock(string $q): array
    {
        return $this->createQueryBuilder('p')
            ->select('
                p.id AS id,
                p.descripcion_Pd AS nombre,
                p.pvp_Pd AS pvp,
                tp.decripcion_Tp AS tipo,
                COALESCE(SUM(
                    CASE
                        WHEN sm.tipoMovimiento IN (:entradas) THEN sm.cantidad
                        WHEN sm.tipoMovimiento IN (:salidas) THEN -sm.cantidad
                        ELSE 0
                    END
                ), 0) AS stock
            ')
            ->leftJoin('p.tipo_pd_id', 'tp')
            ->leftJoin(\App\Entity\StockMovimiento::class, 'sm', 'WITH', 'sm.producto = p')
            ->where('p.descripcion_Pd LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->setParameter('entradas', [
                \App\Entity\StockMovimiento::TIPO_ENTRADA_FACTURA,
                \App\Entity\StockMovimiento::TIPO_ENTRADA_MANUAL,
                \App\Entity\StockMovimiento::TIPO_AJUSTE_POSITIVO,
                \App\Entity\StockMovimiento::TIPO_INVENTARIO_INICIAL,
            ])
            ->setParameter('salidas', [
                \App\Entity\StockMovimiento::TIPO_SALIDA_OBRA,
                \App\Entity\StockMovimiento::TIPO_SALIDA_TIENDA,
                \App\Entity\StockMovimiento::TIPO_AJUSTE_NEGATIVO,
            ])
            ->groupBy('p.id')
            ->addGroupBy('tp.id')
            ->orderBy('stock', 'DESC')
            ->addOrderBy('p.descripcion_Pd', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getArrayResult();
    }   

    // /**
    //  * @return Productos[] Returns an array of Productos objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Productos
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
