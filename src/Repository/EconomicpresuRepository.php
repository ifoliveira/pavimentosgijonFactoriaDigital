<?php

namespace App\Repository;

use App\Entity\Economicpresu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Economicpresu>
 *
 * @method Economicpresu|null find($id, $lockMode = null, $lockVersion = null)
 * @method Economicpresu|null findOneBy(array $criteria, array $orderBy = null)
 * @method Economicpresu[]    findAll()
 * @method Economicpresu[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EconomicpresuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Economicpresu::class);
    }

    public function add(Economicpresu $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Economicpresu $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function cobroTotal(?int $anio = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

            // Usar el año actual si no se pasa uno
            $anio = $anio ?? (int) date('Y');

        $sql = '
            SELECT SUM(e.importe_eco) AS cobrototalMo
            FROM economicpresu e
            INNER JOIN presupuestos p ON p.id = e.idpresu_eco_id
            INNER JOIN cestas c ON c.id = p.ticket_id
            WHERE e.aplica_eco = :modo
            AND c.estado_cs = :estado
            AND YEAR(e.timestamp) = :anio
        ';

        $params = [
            'modo' => 'M',
            'estado' => 2,
            'anio' => $anio,
        ];

        return $conn->fetchAssociative($sql, $params);
    }

    public function pagototal(?int $anio = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Usar el año actual si no se pasa uno
        $anio = $anio ?? (int) date('Y');

        $sql = '
            SELECT SUM(e.importe_eco) AS pagototalMo
            FROM economicpresu e
            INNER JOIN presupuestos p ON p.id = e.idpresu_eco_id
            INNER JOIN cestas c ON c.id = p.ticket_id
            WHERE e.aplica_eco = "E"
            AND c.estado_cs = 2
            AND YEAR(e.timestamp) = :anio
        ';

        $params = [
            'anio' => $anio,
        ];

        return $conn->fetchAssociative($sql, $params);
    }
    
    public function pendienteCobro()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_eco) as cobropdteMo FROM economicpresu p
        WHERE aplica_eco = "M"
          AND estado_eco = "1"
            ';

        return $conn->fetchAssociative($sql);


    }     
    
    public function pendientePago()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_eco) as pagopdteMo FROM economicpresu p
        WHERE aplica_eco = "E"
          AND estado_eco = "1"
            ';

        return $conn->fetchAssociative($sql);


    }      

    public function pendienteYaCobrado(?int $anio = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Usar el año actual si no se pasa uno
        $anio = $anio ?? (int) date('Y');

        $sql = '
            SELECT SUM(e.importe_pg) AS pagototalPdte
            FROM pagos e
            INNER JOIN cestas c ON c.id = e.cesta_id
            Where c.estado_cs = 3
            AND YEAR(c.timestamp_cs) = :anio
        ';

        $params = [
            'anio' => $anio,
        ];

        return $conn->fetchAssociative($sql, $params);
    }

    public function getImporteManoObraPendiente(int $presupuestoId): float
    {
        $qb = $this->createQueryBuilder('e')
            ->select('SUM(e.importeEco)')
            ->where('e.aplicaEco = :aplica')
            ->andWhere('e.estadoEco = :estado')
            ->andWhere('e.idpresuEco = :presupuestoId')
            ->setParameters([
                'aplica' => 'M',
                'estado' => 1,
                'presupuestoId' => $presupuestoId
            ]);
    
        return (float) $qb->getQuery()->getSingleScalarResult();
    }    
//    /**
//     * @return Economicpresu[] Returns an array of Economicpresu objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Economicpresu
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
