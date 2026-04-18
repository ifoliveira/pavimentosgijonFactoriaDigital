<?php

namespace App\Repository;

use App\Entity\DocumentoLinea;
use App\Entity\Documento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentoLineaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentoLinea::class);
    }

    public function findByDocumento(Documento $documento): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.documento = :doc')
            ->setParameter('doc', $documento)
            ->orderBy('l.posicion', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLineasConStockPendiente(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.afectaStock = true')
            ->andWhere('l.stockMovido = false')
            ->getQuery()
            ->getResult();
    }
}