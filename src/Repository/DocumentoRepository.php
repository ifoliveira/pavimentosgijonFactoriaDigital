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

    /** Deuda de documentos independientes; los proyectos se calculan en conjunto. */
    public function getTotalPendienteCobro(): string
    {
        return (string) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(CASE WHEN d.total > d.totalCobrado THEN d.total - d.totalCobrado ELSE 0 END), 0)')
            ->andWhere('d.tipoDocumento IN (:tipos)')
            ->andWhere('d.proyecto IS NULL')
            ->setParameter('tipos', ['factura', 'ticket', 'ticket_rectificativo'])
            ->getQuery()
            ->getSingleScalarResult();
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
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findTicketDeProyecto(Proyecto $proyecto): ?Documento
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.proyecto = :proyecto')
            ->andWhere('d.tipoDocumento = :tipo')
            ->setParameter('proyecto', $proyecto)
            ->setParameter('tipo', 'ticket')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(1)
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
            ->setParameter('estados', ['convertido', 'aceptado', 'borrador', 'rechazado', 'entregado'])
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

    public function findTicketsPorPeriodo(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        return $this->createQueryBuilder('d')
            ->orWhere('d.tipoDocumento = :tipo')
            ->orWhere('d.tipoDocumento = :tipo2')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipo', 'ticket')
            ->setParameter('tipo2', 'ticket_rectificativo')
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->orderBy('d.fechaEmision', 'ASC')
            ->addOrderBy('d.numero', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getResumenTicketsPorPeriodo(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS numeroTickets')
            ->addSelect('COALESCE(SUM(d.baseImponible), 0) AS totalBase')
            ->addSelect('COALESCE(SUM(d.totalIva), 0) AS totalIva')
            ->addSelect('COALESCE(SUM(d.total), 0) AS totalVentas')
            ->addSelect('COALESCE(SUM(d.totalCobrado), 0) AS totalCobrado')
            ->addSelect('COALESCE(SUM(d.totalCoste), 0) AS totalCoste')
            ->andWhere('d.tipoDocumento IN (:tipos)')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipos', ['ticket', 'ticket_rectificativo'])
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->getQuery()
            ->getSingleResult();
    }    

    /** Facturación emitida desde proyectos, separada de los tickets de tienda. */
    public function getResumenFacturacionProyectosPorPeriodo(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS numeroDocumentos')
            ->addSelect('COALESCE(SUM(d.baseImponible), 0) AS totalBase')
            ->addSelect('COALESCE(SUM(d.totalIva), 0) AS totalIva')
            ->addSelect('COALESCE(SUM(d.total), 0) AS totalVentas')
            ->addSelect('COALESCE(SUM(d.totalCobrado), 0) AS totalCobrado')
            ->andWhere('d.proyecto IS NOT NULL')
            ->andWhere('d.tipoDocumento IN (:tipos)')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipos', ['factura', 'ticket', 'ticket_rectificativo'])
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->getQuery()
            ->getSingleResult();
    }

    /** IVA de documentos por emisión, independientemente de los cobros. */
    public function getResumenIvaTrimestral(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        // La conversión genera una factura en borrador: su presupuesto convertido
        // acredita la aceptación. EXISTS evita duplicarla por presupuestos adicionales.
        $filas = $this->createQueryBuilder('d')
            ->select('d.fechaEmision, d.baseImponible, d.totalIva, d.total')
            ->andWhere("d.tipoDocumento IN (:tickets) OR (
                d.tipoDocumento = :factura AND (
                    d.estadoComercial = :aceptado OR (
                        d.estadoComercial IN (:estadosGenerados) AND EXISTS (
                            SELECT p.id FROM App\\Entity\\Documento p
                            WHERE p.facturaVinculada = d
                            AND p.tipoDocumento = :presupuesto
                            AND p.estadoComercial = :convertido
                        )
                    )
                )
            )")
            ->andWhere('d.fechaEmision >= :desde')
            ->andWhere('d.fechaEmision < :hasta')
            ->setParameter('tickets', ['ticket', 'ticket_rectificativo'])
            ->setParameter('factura', 'factura')
            ->setParameter('aceptado', 'aceptado')
            ->setParameter('estadosGenerados', ['borrador', 'entregado'])
            ->setParameter('presupuesto', 'presupuesto')
            ->setParameter('convertido', 'convertido')
            ->setParameter('desde', $fechaDesde)->setParameter('hasta', $fechaHasta)
            ->orderBy('d.fechaEmision', 'ASC')->getQuery()->getArrayResult();

        $trimestres = [];
        foreach ($filas as $fila) {
            $trimestre = (int) ceil((int) $fila['fechaEmision']->format('n') / 3);
            $trimestres[$trimestre] ??= [
                'trimestre' => 'T' . $trimestre,
                'baseImponible' => '0.00',
                'ivaRepercutido' => '0.00',
                'total' => '0.00',
            ];
            foreach (['baseImponible' => 'baseImponible', 'ivaRepercutido' => 'totalIva', 'total' => 'total'] as $clave => $campo) {
                $trimestres[$trimestre][$clave] = bcadd($trimestres[$trimestre][$clave], (string) $fila[$campo], 2);
            }
        }

        return array_values($trimestres);
    }

    public function getResumenTicketsTrimestral(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        $filas = $this->createQueryBuilder('d')
            ->select('d.fechaEmision, d.baseImponible, d.totalIva, d.total')
            ->andWhere('d.tipoDocumento IN (:tipos)')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipos', ['ticket', 'ticket_rectificativo'])
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->orderBy('d.fechaEmision', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $trimestres = [];
        foreach ($filas as $fila) {
            $fecha = $fila['fechaEmision'];
            $mes = $fecha instanceof \DateTimeInterface ? (int) $fecha->format('n') : (int) date('n', strtotime((string) $fecha));
            $trimestre = (int) floor(($mes - 1) / 3) + 1;
            $trimestres[$trimestre] ??= [
                'trimestre' => 'T' . $trimestre,
                'baseImponible' => 0.0,
                'ivaCobrado' => 0.0,
                'total' => 0.0,
            ];
            $trimestres[$trimestre]['baseImponible'] += (float) $fila['baseImponible'];
            $trimestres[$trimestre]['ivaCobrado'] += (float) $fila['totalIva'];
            $trimestres[$trimestre]['total'] += (float) $fila['total'];
        }

        return array_values($trimestres);
    }

    public function findFacturasPorPeriodo(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        return $this->createQueryBuilder('d')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipo', 'factura')
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->orderBy('d.fechaEmision', 'ASC')
            ->addOrderBy('d.numero', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getResumenFacturasPorPeriodo(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS numeroFacturas')
            ->addSelect('COALESCE(SUM(d.baseImponible), 0) AS totalBase')
            ->addSelect('COALESCE(SUM(d.totalIva), 0) AS totalIva')
            ->addSelect('COALESCE(SUM(d.total), 0) AS totalVentas')
            ->addSelect('COALESCE(SUM(d.totalCobrado), 0) AS totalCobrado')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipo', 'factura')
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->getQuery()
            ->getSingleResult();
    }      
}
