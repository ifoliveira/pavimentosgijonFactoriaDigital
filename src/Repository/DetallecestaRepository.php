<?php

namespace App\Repository;

use App\Entity\Detallecesta;
use App\Entity\Cestas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Detallecesta|null find($id, $lockMode = null, $lockVersion = null)
 * @method Detallecesta|null findOneBy(array $criteria, array $orderBy = null)
 * @method Detallecesta[]    findAll()
 * @method Detallecesta[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DetallecestaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detallecesta::class);
    }

    public function imptotalCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc * (dc.pvpDc - (dc.pvpDc * dc.descuentoDc /100))) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function descuentoCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc * (dc.pvpDc * dc.descuentoDc /100)) as TotalDescuento')
            ->getQuery()
            ->getSingleScalarResult();
    }    

    public function cantotalCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }
    

    public function preciototalCesta($value)
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.cestaDc = :cestaDc')
            ->setParameter('cestaDc', $value)
            ->select('SUM (dc.cantidadDc * dc.precioDc) as TotalDetalle')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function beneficioTotal(?int $anio = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
         
        // Usar el año actual si no se pasa uno
        $anio = $anio ?? (int) date('Y');

        $sql = '
            SELECT 
                SUM((pvp_dc - (pvp_dc * descuento_dc / 100)) * cantidad_dc)  AS pvp, 
                SUM(CASE WHEN precio_dc = 0 THEN pvp_dc * 0.5 * cantidad_dc ELSE precio_dc * cantidad_dc END) AS precio
            FROM 
                detallecesta a 
            INNER JOIN 
                cestas p ON cesta_dc_id = p.id
            WHERE 
                YEAR(fecha_fin_cs) = :anio
                AND estado_cs = 2;
            ';

        // returns an array of arrays (i.e. a raw data set)
        return $conn->fetchAssociative($sql, ['anio' => $anio]);

    }       

    /**
     * @return Detallecesta[]
     */
    public function detallescestaactual()
    {
        $entityManager = $this->getEntityManager();
        $fechaEspecifica = new \DateTime('2024-01-01');

        $query = $entityManager->createQuery(
            'SELECT a
               FROM App\Entity\detallecesta a INNER JOIN App\Entity\cestas p
                WHERE a.cestaDc = p.id
                AND p.estadoCs = 2  
                AND a.precioDc = 0              
                AND a.timestampDc >= :fecha ');
        $query->setParameter('fecha', $fechaEspecifica);

        // returns an array of Product objects
        return $query->getResult();

    }
}
