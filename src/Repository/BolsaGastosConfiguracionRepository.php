<?php

namespace App\Repository;

use App\Entity\BolsaGastosConfiguracion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BolsaGastosConfiguracion> */
class BolsaGastosConfiguracionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BolsaGastosConfiguracion::class);
    }

    /** Consultar la configuración nunca activa la bolsa ni escribe en la base de datos. */
    public function obtener(): BolsaGastosConfiguracion
    {
        return $this->find(1) ?? new BolsaGastosConfiguracion();
    }
}
