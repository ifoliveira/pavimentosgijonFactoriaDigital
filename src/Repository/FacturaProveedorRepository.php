<?php

namespace App\Repository;

use App\Entity\FacturaProveedor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FacturaProveedorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FacturaProveedor::class);
    }

    public function save(FacturaProveedor $facturaProveedor, bool $flush = false): void
    {
        $this->getEntityManager()->persist($facturaProveedor);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FacturaProveedor $facturaProveedor, bool $flush = false): void
    {
        $this->getEntityManager()->remove($facturaProveedor);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findUltimas(int $limite = 100): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function findPendientesAsignacion(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.estadoAsignacion IN (:estados)')
            ->setParameter('estados', [
                'pendiente',
                'parcial',
            ])
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findSiguientePendiente(int $facturaActualId): ?FacturaProveedor
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.id <> :id')
            ->andWhere('f.estadoAsignacion IN (:estados)')
            ->setParameter('id', $facturaActualId)
            ->setParameter('estados', [
                'pendiente',
                'parcial',
            ])
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function buscar(?string $q = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        if ($q) {
            $qb
                ->andWhere('f.proveedorNombre LIKE :q OR f.numeroFactura LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findPorEstadoAsignacion(string $estado): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.estadoAsignacion = :estado')
            ->setParameter('estado', $estado)
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findEntreFechas(
        \DateTimeInterface $desde,
        \DateTimeInterface $hasta
    ): array {
        return $this->createQueryBuilder('f')
            ->andWhere('f.fechaFactura >= :desde')
            ->andWhere('f.fechaFactura <= :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('f.fechaFactura', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function contarPendientesAsignacion(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.estadoAsignacion IN (:estados)')
            ->setParameter('estados', [
                'pendiente',
                'parcial',
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUltimaImportada(): ?FacturaProveedor
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAsignadas(int $limite = 100): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.estadoAsignacion = :estado')
            ->setParameter('estado', 'asignada')
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function findRevisadas(int $limite = 100): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.estadoAsignacion = :estado')
            ->setParameter('estado', 'revisada')
            ->orderBy('f.fechaCreacion', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}   