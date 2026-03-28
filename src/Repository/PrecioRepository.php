<?php

namespace App\Repository;

use App\Entity\Precio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Precio>
 *
 * @method Precio|null find($id, $lockMode = null, $lockVersion = null)
 * @method Precio|null findOneBy(array $criteria, array $orderBy = null)
 * @method Precio[]    findAll()
 * @method Precio[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrecioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Precio::class);
    }

    public function get(string $clave, string $tipo): float
    {
        $precio = $this->findOneBy(['clave' => $clave, 'tipoReforma' => $tipo]);

        // Si no encuentra el precio específico, busca en 'todos'
        if (!$precio) {
            $precio = $this->findOneBy(['clave' => $clave, 'tipoReforma' => 'todos']);
        }

        if (!$precio) {
            throw new \RuntimeException("Precio no encontrado: '$clave' para '$tipo'");
        }

        return (float) $precio->getValor();
    }    

    public function getPorGrupoYTipo(): array
    {
        $todos = $this->findBy([], ['tipoReforma' => 'ASC', 'grupo' => 'ASC', 'clave' => 'ASC']);
        $agrupados = [];
        foreach ($todos as $precio) {
            $agrupados[$precio->getTipoReforma()][$precio->getGrupo()][] = $precio;
        }
        return $agrupados;
    }    
//    /**
//     * @return Precio[] Returns an array of Precio objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Precio
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
