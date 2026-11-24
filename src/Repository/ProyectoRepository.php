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

    /** Cada proyecto aporta una sola deuda, aunque tenga factura y ticket. */
    public function getTotalPendienteCobro(): string
    {
        // Consultas separadas para no multiplicar documentos por cobros al unirlos.
        $proyectos = $this->createQueryBuilder('p')
            ->select('p.id, p.totalPresupuestado, p.totalFacturado, COALESCE(SUM(c.importeBruto), 0) AS cobrado')
            ->leftJoin('p.cobros', 'c')
            ->groupBy('p.id, p.totalPresupuestado, p.totalFacturado')->getQuery()->getArrayResult();
        $documentos = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'IDENTITY(d.proyecto) AS proyecto, '
                . 'SUM(d.total) AS total, '
                . 'COUNT(anticipo.id) AS anticipos, '
                . "SUM(CASE WHEN anticipo.id IS NULL AND d.tipoDocumento = 'factura' THEN 1 ELSE 0 END) AS finales"
            )
            ->from(\App\Entity\Documento::class, 'd')
            ->leftJoin('d.cobroAnticipo', 'anticipo')
            ->where('d.proyecto IS NOT NULL')->andWhere('d.tipoDocumento IN (:tipos)')
            ->setParameter('tipos', ['factura', 'ticket', 'ticket_rectificativo'])
            ->groupBy('d.proyecto')->getQuery()->getArrayResult();
        $porProyecto = array_column($documentos, null, 'proyecto');
        $cobrosDirectos = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(d.proyecto) AS proyecto, SUM(c.importeBruto) AS cobrado')
            ->from(\App\Entity\DocumentoCobro::class, 'c')
            ->join('c.documento', 'd')
            ->where('d.proyecto IS NOT NULL')
            ->groupBy('d.proyecto')->getQuery()->getArrayResult();
        $cobrosDirectos = array_column($cobrosDirectos, 'cobrado', 'proyecto');
        $total = '0.00';
        foreach ($proyectos as $proyecto) {
            $fiscal = $porProyecto[$proyecto['id']] ?? null;
            $tieneAnticipos = (int) ($fiscal['anticipos'] ?? 0) > 0;

            // Hasta la factura final, la deuda parte del importe contratado.
            // Después, anticipos + factura final suman la facturación completa.
            $importe = $tieneAnticipos && (int) $fiscal['finales'] === 0
                ? $proyecto['totalPresupuestado']
                : ($fiscal['total'] ?? $proyecto['totalPresupuestado']);
            // Los totales de las facturas también reflejan cobros de proyecto.
            // Solo sumamos aquí apuntes directos para no duplicar el dinero.
            $cobradoDocumentos = (string) ($cobrosDirectos[$proyecto['id']] ?? '0');
            $cobrado = bcadd((string) $proyecto['cobrado'], $cobradoDocumentos, 2);
            $pendiente = bcsub(number_format((float) $importe, 2, '.', ''), $cobrado, 2);
            if (bccomp($pendiente, '0', 2) > 0) {
                $total = bcadd($total, $pendiente, 2);
            }
        }

        return $total;
    }

    /**
     * Proyectos que han generado facturación en el periodo indicado.
     *
     * Los gastos se cargan junto al proyecto para poder calcular el margen sin
     * hacer una consulta adicional por cada obra en el resumen económico.
     *
     * @return Proyecto[]
     */
    public function findConFacturacionPorPeriodo(
        \DateTimeInterface $fechaDesde,
        \DateTimeInterface $fechaHasta
    ): array {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.documentos', 'd')
            ->leftJoin('p.gastos', 'g')
            ->addSelect('g')
            ->andWhere('d.tipoDocumento IN (:tipos)')
            ->andWhere('d.fechaEmision >= :fechaDesde')
            ->andWhere('d.fechaEmision < :fechaHasta')
            ->setParameter('tipos', ['factura', 'ticket', 'ticket_rectificativo'])
            ->setParameter('fechaDesde', $fechaDesde)
            ->setParameter('fechaHasta', $fechaHasta)
            ->distinct()
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
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

    public function findAceptadosConCobros(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.totalCobrado > :tipo')
            ->andWhere('p.fechaInicio >= :fechaInicio')
            ->setParameter('tipo', 0)
            ->setParameter('fechaInicio', new \DateTime('-120 days'))
            ->distinct()
            ->orderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();
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

    public function countProyectosConPresupuestoEntregado(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial IN (:estados)')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estados', ['entregado'])
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function countProyectosPendientesCobro(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento = :tipo')
            ->andWhere('(d.estadoCobro IN (:estados) OR d.totalCobrado < d.total)')
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

    public function findTodosProyectosDashboard(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.cliente', 'c')
            ->addSelect('c')
            ->orderBy('p.creadoEn', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUltimosProyectosDashboard(int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.cliente', 'c')
            ->addSelect('c')
            ->innerJoin('p.documentos', 'd')
            ->andWhere('d.tipoDocumento in (:tipoFactura)')
            ->setParameter('tipoFactura', ['factura', 'ticket'])
            ->orderBy('p.creadoEn', 'DESC')
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

        return $qb->getQuery()->getResult();
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
