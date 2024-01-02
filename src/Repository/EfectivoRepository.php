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

        $sql = 'SELECT sum(importe_ef) as efectivototal  FROM efectivo p';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

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
