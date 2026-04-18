<?php

namespace App\Repository;

use App\Entity\Proyecto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProyectoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proyecto::class);
    }

    public function countProyectosSinPresupuesto(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->leftJoin('p.documentos', 'd', 'WITH', 'd.tipoDocumento = :tipo')
            ->andWhere('d.id IS NULL')
            ->setParameter('tipo', 'presupuesto')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProyectosConPresupuestoBorrador(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial = :estado')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estado', 'borrador')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProyectosConPresupuestoAceptadoOConvertido(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial IN (:estados)')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estados', ['aceptado', 'convertido'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProyectosFacturados(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento = :tipo')
            ->setParameter('tipo', 'factura')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProyectosPendientesCobro(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoCobro IN (:estados)')
            ->setParameter('tipo', 'factura')
            ->setParameter('estados', ['pendiente', 'parcial'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProyectosCerrados(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.fechaFinReal IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUltimosProyectosDashboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.cliente', 'c')
            ->addSelect('c')
            ->orderBy('p.actualizadoEn', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function buscarProyectosDashboard(
        ?string $nombre,
        ?string $cliente,
        ?string $telefono,
        ?string $situacion
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.cliente', 'c')
            ->addSelect('c')
            ->orderBy('p.actualizadoEn', 'DESC');

        if ($nombre) {
            $qb->andWhere('p.nombre LIKE :nombre')
                ->setParameter('nombre', '%' . $nombre . '%');
        }

        if ($cliente) {
            $qb->andWhere('(c.nombreCl LIKE :cliente OR c.apellidosCl LIKE :cliente)')
                ->setParameter('cliente', '%' . $cliente . '%');
        }

        if ($telefono) {
            $qb->andWhere('(c.telefono1Cl LIKE :telefono OR c.telefono2Cl LIKE :telefono)')
                ->setParameter('telefono', '%' . $telefono . '%');
        }

        // La situación la filtraremos mejor en el servicio
        // porque depende de combinar documentos + proyecto.

        return $qb->setMaxResults(25)->getQuery()->getResult();
    }

    public function countProyectosAceptadosSinFactura(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->leftJoin('p.documentos', 'pres', 'WITH', 'pres.tipoDocumento = :tipoPres')
            ->leftJoin('p.documentos', 'fac', 'WITH', 'fac.tipoDocumento = :tipoFac')
            ->andWhere('pres.estadoComercial IN (:estadosAceptados)')
            ->andWhere('fac.id IS NULL')
            ->setParameter('tipoPres', 'presupuesto')
            ->setParameter('tipoFac', 'factura')
            ->setParameter('estadosAceptados', ['aceptado', 'convertido'])
            ->getQuery()
            ->getSingleScalarResult();
    }    
}