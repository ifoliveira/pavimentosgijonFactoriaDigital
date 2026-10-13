<?php

namespace App\Repository;

use App\Entity\TelegramPresupuestoRecepcion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TelegramPresupuestoRecepcionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramPresupuestoRecepcion::class);
    }

    public function findOneByBotAndUpdate(string $botId, int $updateId): ?TelegramPresupuestoRecepcion
    {
        return $this->findOneBy(['botId' => $botId, 'updateId' => $updateId]);
    }

    public function ultimoUpdateProcesado(string $botId): ?int
    {
        $value = $this->createQueryBuilder('r')
            ->select('MAX(r.ultimoUpdateProcesado)')
            ->andWhere('r.botId = :botId')
            ->setParameter('botId', $botId)
            ->getQuery()
            ->getSingleScalarResult();

        return $value !== null ? (int) $value : null;
    }
}
