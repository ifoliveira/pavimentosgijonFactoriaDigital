<?php

namespace App\Repository;

use App\Entity\Documento;
use App\Entity\Proyecto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Documento::class);
    }

    public function findDocumentosDeProyectoParaDashboard(\App\Entity\Proyecto $proyecto): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.proyecto = :proyecto')
            ->setParameter('proyecto', $proyecto)
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }    

    public function findUltimoNumeroPorSerie(string $serie): ?int
    {
        return $this->createQueryBuilder('d')
            ->select('MAX(d.numero)')
            ->where('d.serie = :serie')
            ->setParameter('serie', $serie)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFacturaDeProyecto(Proyecto $proyecto): ?Documento
    {
        return $this->createQueryBuilder('d')
            ->where('d.proyecto = :proyecto')
            ->andWhere('d.tipoDocumento = :tipo')
            ->setParameter('proyecto', $proyecto)
            ->setParameter('tipo', 'factura')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPresupuestoInicialDeProyecto(Proyecto $proyecto): ?Documento
    {
        return $this->createQueryBuilder('d')
            ->where('d.proyecto = :proyecto')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial IN (:estados)')
            ->setParameter('proyecto', $proyecto)
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estados', ['convertido', 'aceptado'])
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPresupuestosAdicionalesDeFactura(Documento $factura): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.facturaVinculada = :factura')
            ->andWhere('d.tipoDocumento = :tipo')
            ->setParameter('factura', $factura)
            ->setParameter('tipo', 'presupuesto')
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFiltros(array $filtros): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.cliente', 'c')
            ->addSelect('c')
            ->orderBy('d.creadoEn', 'DESC');

        if (!empty($filtros['busqueda'])) {
            $qb->andWhere('
                c.direccionCl LIKE :busqueda
                OR c.nombreCl LIKE :busqueda
                OR c.apellidosCl LIKE :busqueda
                OR d.serie LIKE :busqueda
                OR CAST(d.numero AS string) LIKE :busqueda
            ')
            ->setParameter('busqueda', '%' . $filtros['busqueda'] . '%');
        }

        if (!empty($filtros['tipo'])) {
            $qb->andWhere('d.tipoDocumento = :tipo')
                ->setParameter('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['estadoComercial'])) {
            $qb->andWhere('d.estadoComercial = :estadoComercial')
                ->setParameter('estadoComercial', $filtros['estadoComercial']);
        }

        if (!empty($filtros['estadoCobro'])) {
            $qb->andWhere('d.estadoCobro = :estadoCobro')
                ->setParameter('estadoCobro', $filtros['estadoCobro']);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByTipoAndEstadoComercial(string $tipoDocumento, string $estadoComercial): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial = :estado')
            ->setParameter('tipo', $tipoDocumento)
            ->setParameter('estado', $estadoComercial)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByTipoAndEstadoCobro(string $tipoDocumento, string $estadoCobro): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoCobro = :estado')
            ->setParameter('tipo', $tipoDocumento)
            ->setParameter('estado', $estadoCobro)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPresupuestosEntregadosPendientes(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial = :estado')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estado', 'entregado')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countFacturasPendientesCobro(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoCobro IN (:estados)')
            ->setParameter('tipo', 'factura')
            ->setParameter('estados', ['pendiente', 'parcial'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDocumentosSinCliente(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.cliente IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDocumentosSinLineas(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(DISTINCT d.id)')
            ->leftJoin('d.lineas', 'l')
            ->andWhere('l.id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUltimosDocumentos(int $limit = 8): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.cliente', 'c')
            ->addSelect('c')
            ->orderBy('d.creadoEn', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function buscarDocumentosDashboard(
        ?string $numero,
        ?string $cliente,
        ?string $telefono,
        ?string $estado,
        ?string $tipo
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.cliente', 'c')
            ->addSelect('c')
            ->orderBy('d.creadoEn', 'DESC');

        if ($numero) {
            if (str_contains($numero, '-')) {
                $qb->andWhere("CONCAT(d.serie, '-', d.numero) LIKE :numeroCompleto")
                    ->setParameter('numeroCompleto', '%' . $numero . '%');
            } else {
                $qb->andWhere('CAST(d.numero AS string) LIKE :numero')
                    ->setParameter('numero', '%' . $numero . '%');
            }
        }

        if ($cliente) {
            $qb->andWhere('(c.nombreCl LIKE :cliente OR c.apellidosCl LIKE :cliente)')
                ->setParameter('cliente', '%' . $cliente . '%');
        }

        if ($telefono) {
            $qb->andWhere('(c.telefono1Cl LIKE :telefono OR c.telefono2Cl LIKE :telefono)')
                ->setParameter('telefono', '%' . $telefono . '%');
        }

        if ($estado) {
            $qb->andWhere('(d.estadoComercial = :estado OR d.estadoCobro = :estado)')
                ->setParameter('estado', $estado);
        }

        if ($tipo) {
            $qb->andWhere('d.tipoDocumento = :tipo')
                ->setParameter('tipo', $tipo);
        }

        return $qb->setMaxResults(25)->getQuery()->getResult();
    }
}