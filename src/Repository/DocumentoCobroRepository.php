<?php

namespace App\Repository;

use App\Entity\DocumentoCobro;
use App\Entity\Documento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentoCobroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentoCobro::class);
    }

    public function findByDocumento(Documento $documento): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.documento = :doc')
            ->setParameter('doc', $documento)
            ->orderBy('c.fecha', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalCobradoDocumento(Documento $documento): string
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.importeBruto) as total')
            ->where('c.documento = :doc')
            ->setParameter('doc', $documento)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }
}