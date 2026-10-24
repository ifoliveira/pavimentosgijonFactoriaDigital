<?php

namespace App\Repository;

use App\Entity\Banco;
use app\Entity\Detallecesta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Banco|null find($id, $lockMode = null, $lockVersion = null)
 * @method Banco|null findOneBy(array $criteria, array $orderBy = null)
 * @method Banco[]    findAll()
 * @method Banco[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BancoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banco::class);
    }

    /**
     * @return totalBanco Returns an array of Banco objects
    */

    public function totalBanco()
      {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT sum(importe_bn), max(fecha_bn) FROM banco p';
        $stmt = $conn->prepare($sql);
        $stmt->executeQuery();

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative('SELECT sum(importe_bn), max(fecha_bn) FROM banco p');
        
      }

    /**
     * Suma los movimientos bancarios por categoría para un ejercicio.
     * El signo se conserva tal y como fue importado/registrado.
     */
    public function totalesPorCategoria(int $anio): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT COALESCE(tm.descripcion_tm, \'Sin categoría\') AS categoria,
                    COALESCE(SUM(b.importe_bn), 0) AS importe
             FROM banco b
             LEFT JOIN tiposmovimiento tm ON tm.id = b.categoria_bn
             WHERE YEAR(b.fecha_bn) = :anio
             GROUP BY tm.id, tm.descripcion_tm
             ORDER BY categoria',
            ['anio' => $anio]
        );
    }

    public function ventamesBanco()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_bn), year(fecha_bn), month(fecha_bn) as mes FROM banco p
        WHERE categoria_bn = 3
          AND YEAR(fecha_bn) = YEAR(CURDATE())
        GROUP BY YEAR(fecha_bn) , MONTH(fecha_bn);
            ';
     
        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAllAssociative($sql);

    }


    public function manoobraBanco()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_bn) as importe FROM banco p
        WHERE categoria_bn = 1
          AND YEAR(fecha_bn) = YEAR(CURDATE());
            ';
     //   $stmt = $conn->prepare($sql);
     //   $stmt->execute();

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }

    public function ventasBanco()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT sum(importe_bn) as importe FROM banco p
        WHERE categoria_bn = 3
          AND YEAR(fecha_bn) = YEAR(CURDATE());
            ';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }    

    public function fechamaxima()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT date_add(MAX(fecha_bn), interval 1 day) as fechamaxima FROM banco p
         ';
     //   $stmt = $conn->prepare($sql);
     //   $stmt->execute();

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql);

    }

    // /**
    //  * @return Banco[] Returns an array of Banco objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Banco
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
