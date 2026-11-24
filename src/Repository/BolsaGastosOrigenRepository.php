<?php

namespace App\Repository;

use App\Entity\Documento;
use App\Entity\FacturaProveedor;
use Doctrine\ORM\EntityManagerInterface;

/** Fuentes originales de la previsión, independientes de la asignación de costes. */
class BolsaGastosOrigenRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return Documento[] */
    public function findPresupuestos(\DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->em->createQueryBuilder()
            ->select('d', 'p', 'l')
            ->from(Documento::class, 'd')
            ->innerJoin('d.proyecto', 'p')
            ->leftJoin('d.lineas', 'l')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial IN (:estados)')
            ->andWhere('d.fechaAceptacion >= :desde')
            ->andWhere('d.fechaAceptacion <= :hasta')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estados', ['aceptado', 'convertido', 'aplicado'])
            ->setParameter('desde', $desde->format('Y-m-d'))
            ->setParameter('hasta', $hasta->format('Y-m-d'))
            ->orderBy('d.fechaAceptacion', 'ASC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return FacturaProveedor[] */
    public function findFacturas(\DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->em->createQueryBuilder()
            ->select('f', 'v')
            ->from(FacturaProveedor::class, 'f')
            ->leftJoin('f.forecasts', 'v')
            ->andWhere('f.fechaFactura >= :desde')
            ->andWhere('f.fechaFactura <= :hasta')
            ->setParameter('desde', $desde->format('Y-m-d'))
            ->setParameter('hasta', $hasta->format('Y-m-d'))
            ->orderBy('f.fechaFactura', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function findProveedores(): array
    {
        $filas = $this->em->createQueryBuilder()
            ->select('DISTINCT f.proveedorNombre AS nombre')
            ->from(FacturaProveedor::class, 'f')
            ->andWhere('f.proveedorNombre IS NOT NULL')
            ->andWhere("TRIM(f.proveedorNombre) <> ''")
            ->orderBy('f.proveedorNombre', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $nombres = array_values(array_unique(array_map(static fn (array $fila): string => trim($fila['nombre']), $filas)));
        sort($nombres, SORT_NATURAL | SORT_FLAG_CASE);

        return $nombres;
    }
}
