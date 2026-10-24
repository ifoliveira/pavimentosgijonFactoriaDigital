<?php

namespace App\Repository;

use App\Entity\Efectivo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Efectivo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Efectivo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Efectivo[]    findAll()
 * @method Efectivo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EfectivoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Efectivo::class);
    }

    public function totalefectivo()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT sum(importe_ef) as efectivototal  FROM efectivo p
                 WHERE fecha_ef >= "2024-01-01"';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }

    /**
     * Suma los movimientos de efectivo por categoría para un ejercicio.
     * El signo se conserva tal y como fue registrado.
     */
    public function totalesPorCategoria(int $anio): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT COALESCE(tm.descripcion_tm, \'Sin categoría\') AS categoria,
                    COALESCE(SUM(e.importe_ef), 0) AS importe
             FROM efectivo e
             LEFT JOIN tiposmovimiento tm ON tm.id = e.tipoEf
             WHERE YEAR(e.fecha_ef) = :anio
             GROUP BY tm.id, tm.descripcion_tm
             ORDER BY categoria',
            ['anio' => $anio]
        );
    }

    public function manoobraEfectivo()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_ef) FROM efectivo p
        WHERE tipoEf = 1
          AND YEAR(fecha_ef) = YEAR(CURDATE());
            ';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }

    public function ventasEfectivo()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_ef) FROM efectivo p
        WHERE tipoEf = 3
          AND YEAR(fecha_ef) = YEAR(CURDATE());
            ';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }    

}
