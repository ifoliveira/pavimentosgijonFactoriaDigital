<?php

namespace App\Repository;

use App\Entity\SerieDocumento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SerieDocumentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SerieDocumento::class);
    }

    public function findOneByCodigo(string $codigo): ?SerieDocumento
    {
        return $this->findOneBy(['codigo' => $codigo]);
    }
}