<?php

namespace App\Repository;

use App\Entity\StockMovimiento;
use App\Entity\Productos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMovimiento>
 *
 * @method StockMovimiento|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockMovimiento|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockMovimiento[]    findAll()
 * @method StockMovimiento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockMovimientoRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovimiento::class);
    }

    public function add(StockMovimiento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(StockMovimiento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findResumenStock(): array
    {
        return $this->createQueryBuilder('m')
            ->select('
                COALESCE(p.id, 0) AS productoId,
                m.descripcionProducto AS descripcion,
                m.referenciaProveedor AS referenciaProveedor,
                fp.id AS facturaProveedorId,
                SUM(
                    CASE
                        WHEN m.tipoMovimiento IN (:entradas) THEN m.cantidad
                        WHEN m.tipoMovimiento IN (:salidas) THEN -m.cantidad
                        ELSE 0
                    END
                ) AS cantidadStock,
                AVG(m.precioCosteUnitario) AS costeMedio,
                SUM(
                    CASE
                        WHEN m.tipoMovimiento IN (:entradas) THEN m.cantidad * m.precioCosteUnitario
                        WHEN m.tipoMovimiento IN (:salidas) THEN -m.cantidad * m.precioCosteUnitario
                        ELSE 0
                    END
                ) AS valorStock
            ')
            ->leftJoin('m.producto', 'p')
            ->leftJoin('m.facturaProveedorLineaAsignacion', 'a')
            ->leftJoin('a.linea', 'l')
            ->leftJoin('l.facturaProveedor', 'fp')
            ->groupBy('productoId, m.descripcionProducto, m.referenciaProveedor, fp.id')
            ->having('cantidadStock <> 0')
            ->setParameter('entradas', [
                StockMovimiento::TIPO_ENTRADA_FACTURA,
                StockMovimiento::TIPO_ENTRADA_MANUAL,
                StockMovimiento::TIPO_AJUSTE_POSITIVO,
                StockMovimiento::TIPO_INVENTARIO_INICIAL,
            ])
            ->setParameter('salidas', [
                StockMovimiento::TIPO_SALIDA_OBRA,
                StockMovimiento::TIPO_SALIDA_TIENDA,
                StockMovimiento::TIPO_AJUSTE_NEGATIVO,
            ])
            ->orderBy('descripcion', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findMovimientosParaAsociarProducto(
        string $descripcion,
        ?string $referenciaProveedor = null,
        ?int $facturaProveedorId = null
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.facturaProveedorLineaAsignacion', 'a')
            ->leftJoin('a.linea', 'l')
            ->leftJoin('l.facturaProveedor', 'fp')
            ->where('m.descripcionProducto = :descripcion')
            ->setParameter('descripcion', $descripcion);

        if ($referenciaProveedor) {
            $qb
                ->andWhere('m.referenciaProveedor = :referenciaProveedor')
                ->setParameter('referenciaProveedor', $referenciaProveedor);
        } else {
            $qb->andWhere('m.referenciaProveedor IS NULL');
        }

        if ($facturaProveedorId) {
            $qb
                ->andWhere('fp.id = :facturaProveedorId')
                ->setParameter('facturaProveedorId', $facturaProveedorId);
        }

        return $qb
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }    

    public function getStockFisicoPorProducto(Productos $producto): float
    {
        $result = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(
                CASE
                    WHEN m.tipoMovimiento IN (:entradas) THEN m.cantidad
                    WHEN m.tipoMovimiento IN (:salidas) THEN -m.cantidad
                    ELSE 0
                END
            ), 0)')
            ->where('m.producto = :producto')
            ->setParameter('producto', $producto)
            ->setParameter('entradas', [
                StockMovimiento::TIPO_ENTRADA_FACTURA,
                StockMovimiento::TIPO_ENTRADA_MANUAL,
                StockMovimiento::TIPO_AJUSTE_POSITIVO,
                StockMovimiento::TIPO_INVENTARIO_INICIAL,
            ])
            ->setParameter('salidas', [
                StockMovimiento::TIPO_SALIDA_OBRA,
                StockMovimiento::TIPO_SALIDA_TIENDA,
                StockMovimiento::TIPO_AJUSTE_NEGATIVO,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }    
}