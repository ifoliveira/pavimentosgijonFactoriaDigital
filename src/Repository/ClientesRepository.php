<?php

namespace App\Repository;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\Presupuestos;
use App\Entity\Proyecto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Clientes|null find($id, $lockMode = null, $lockVersion = null)
 * @method Clientes|null findOneBy(array $criteria, array $orderBy = null)
 * @method Clientes[]    findAll()
 * @method Clientes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Clientes::class);
    }

    /**
     * Obtiene una página del gestor de clientes. La búsqueda se resuelve en la
     * base de datos para que siga siendo útil aunque la agenda crezca.
     *
     * @return array{clientes: Clientes[], pagination: array{page: int, perPage: int, total: int, pages: int}}
     */
    public function findPaginaGestion(
        string $busqueda,
        string $actividad,
        string $orden,
        string $direccion,
        int $page,
        int $perPage = 20
    ): array {
        $actividadPermitida = ['todos', 'con_proyectos', 'sin_proyectos', 'sin_contacto'];
        $ordenPermitido = ['nombre', 'alta'];

        $actividad = in_array($actividad, $actividadPermitida, true) ? $actividad : 'todos';
        $orden = in_array($orden, $ordenPermitido, true) ? $orden : 'nombre';
        $direccion = strtoupper($direccion) === 'DESC' ? 'DESC' : 'ASC';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $qb = $this->createQueryBuilder('c');
        $busqueda = trim($busqueda);

        if ($busqueda !== '') {
            $qb
                ->andWhere(
                    'LOWER(c.nombreCl) LIKE :busqueda'
                    . ' OR LOWER(c.apellidosCl) LIKE :busqueda'
                    . ' OR LOWER(c.emailCl) LIKE :busqueda'
                    . ' OR c.telefono1Cl LIKE :busqueda'
                    . ' OR c.telefono2Cl LIKE :busqueda'
                    . ' OR LOWER(c.ciudadCl) LIKE :busqueda'
                    . ' OR LOWER(c.direccionCl) LIKE :busqueda'
                    . ' OR LOWER(c.dni) LIKE :busqueda'
                )
                ->setParameter('busqueda', '%' . mb_strtolower($busqueda, 'UTF-8') . '%');
        }

        if ($actividad === 'con_proyectos') {
            $qb->andWhere(sprintf(
                'EXISTS (SELECT proyecto.id FROM %s proyecto WHERE proyecto.cliente = c)',
                Proyecto::class
            ));
        }

        if ($actividad === 'sin_proyectos') {
            $qb->andWhere(sprintf(
                'NOT EXISTS (SELECT proyecto.id FROM %s proyecto WHERE proyecto.cliente = c)',
                Proyecto::class
            ));
        }

        if ($actividad === 'sin_contacto') {
            $qb->andWhere(
                "(c.telefono1Cl IS NULL OR c.telefono1Cl = '')"
                . " AND (c.telefono2Cl IS NULL OR c.telefono2Cl = '')"
                . " AND (c.emailCl IS NULL OR c.emailCl = '')"
            );
        }

        $total = (int) (clone $qb)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        if ($orden === 'alta') {
            $qb->orderBy('c.timestampaltaCl', $direccion)
                ->addOrderBy('c.id', $direccion);
        } else {
            $qb->orderBy('c.apellidosCl', $direccion)
                ->addOrderBy('c.nombreCl', $direccion)
                ->addOrderBy('c.id', 'ASC');
        }

        /** @var Clientes[] $clientes */
        $clientes = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'clientes' => $clientes,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }

    /** @return array{total: int, conProyectos: int, conContacto: int, sinContacto: int} */
    public function obtenerResumenGestion(): array
    {
        $total = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $conContacto = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where("c.telefono1Cl IS NOT NULL AND c.telefono1Cl <> ''")
            ->orWhere("c.telefono2Cl IS NOT NULL AND c.telefono2Cl <> ''")
            ->orWhere("c.emailCl IS NOT NULL AND c.emailCl <> ''")
            ->getQuery()
            ->getSingleScalarResult();

        $conProyectos = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT IDENTITY(p.cliente))')
            ->from(Proyecto::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'conProyectos' => $conProyectos,
            'conContacto' => $conContacto,
            'sinContacto' => max(0, $total - $conContacto),
        ];
    }

    /**
     * Resume la actividad comercial de varios clientes sin cargar relaciones
     * una a una. Incluye tanto los presupuestos antiguos como los documentos
     * del flujo actual.
     *
     * @param int[] $clienteIds
     * @return array<int, array{proyectos: int, documentos: int, presupuestos: int}>
     */
    public function obtenerResumenActividad(array $clienteIds): array
    {
        $clienteIds = array_values(array_unique(array_filter(
            array_map('intval', $clienteIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($clienteIds === []) {
            return [];
        }

        $resumen = [];
        foreach ($clienteIds as $clienteId) {
            $resumen[$clienteId] = [
                'proyectos' => 0,
                'documentos' => 0,
                'presupuestos' => 0,
            ];
        }

        $proyectos = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.cliente) AS clienteId, COUNT(p.id) AS total')
            ->from(Proyecto::class, 'p')
            ->where('IDENTITY(p.cliente) IN (:clienteIds)')
            ->groupBy('p.cliente')
            ->setParameter('clienteIds', $clienteIds)
            ->getQuery()
            ->getArrayResult();

        foreach ($proyectos as $fila) {
            $resumen[(int) $fila['clienteId']]['proyectos'] = (int) $fila['total'];
        }

        $documentos = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'IDENTITY(d.cliente) AS clienteId, COUNT(d.id) AS total, '
                . "SUM(CASE WHEN d.tipoDocumento = 'presupuesto' THEN 1 ELSE 0 END) AS presupuestos"
            )
            ->from(Documento::class, 'd')
            ->where('IDENTITY(d.cliente) IN (:clienteIds)')
            ->groupBy('d.cliente')
            ->setParameter('clienteIds', $clienteIds)
            ->getQuery()
            ->getArrayResult();

        foreach ($documentos as $fila) {
            $clienteId = (int) $fila['clienteId'];
            $resumen[$clienteId]['documentos'] = (int) $fila['total'];
            $resumen[$clienteId]['presupuestos'] = (int) $fila['presupuestos'];
        }

        $presupuestosAntiguos = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.clientePe) AS clienteId, COUNT(p.id) AS total')
            ->from(Presupuestos::class, 'p')
            ->where('IDENTITY(p.clientePe) IN (:clienteIds)')
            ->groupBy('p.clientePe')
            ->setParameter('clienteIds', $clienteIds)
            ->getQuery()
            ->getArrayResult();

        foreach ($presupuestosAntiguos as $fila) {
            $resumen[(int) $fila['clienteId']]['presupuestos'] += (int) $fila['total'];
        }

        return $resumen;
    }

    /**
     * @return array{proyectos: Proyecto[], documentos: Documento[], presupuestosAntiguos: Presupuestos[]}
     */
    public function obtenerActividadCliente(Clientes $cliente): array
    {
        /** @var Proyecto[] $proyectos */
        $proyectos = $this->getEntityManager()->createQueryBuilder()
            ->select('p, d')
            ->from(Proyecto::class, 'p')
            ->leftJoin('p.documentos', 'd')
            ->where('p.cliente = :cliente')
            ->setParameter('cliente', $cliente)
            ->orderBy('p.creadoEn', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var Documento[] $documentos */
        $documentos = $this->getEntityManager()->createQueryBuilder()
            ->select('d, p')
            ->from(Documento::class, 'd')
            ->leftJoin('d.proyecto', 'p')
            ->where('d.cliente = :cliente')
            ->setParameter('cliente', $cliente)
            ->orderBy('d.fechaEmision', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var Presupuestos[] $presupuestosAntiguos */
        $presupuestosAntiguos = $this->getEntityManager()->createQueryBuilder()
            ->select('p, e')
            ->from(Presupuestos::class, 'p')
            ->leftJoin('p.estadoPe', 'e')
            ->where('p.clientePe = :cliente')
            ->setParameter('cliente', $cliente)
            ->orderBy('p.fechainiPe', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'proyectos' => $proyectos,
            'documentos' => $documentos,
            'presupuestosAntiguos' => $presupuestosAntiguos,
        ];
    }

    // /**
    //  * @return Clientes[] Returns an array of Clientes objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Clientes
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
