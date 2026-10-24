<?php

namespace App\Repository;

use App\Entity\DocumentoCobro;
use App\Entity\Documento;
use App\Entity\Banco;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentoCobroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentoCobro::class);
    }

    /** Importes pagados por el cliente, incluidas devoluciones y sin descontar comisiones. */
    public function getTotalCobrado(?\DateTimeInterface $desde = null, ?\DateTimeInterface $hasta = null): string
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.importeBruto), 0)');
        if ($desde !== null) {
            $qb->andWhere('c.fecha >= :desde')->setParameter('desde', $desde);
        }
        if ($hasta !== null) {
            $qb->andWhere('c.fecha < :hasta')->setParameter('hasta', $hasta);
        }

        return (string) $qb->getQuery()->getSingleScalarResult();
    }

    /** Cobros de tickets TPV, agrupados por mes según la fecha real de cobro. */
    public function getCobrosTpvPorMes(\DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        $cobros = $this->createQueryBuilder('c')
            ->join('c.documento', 'd')
            ->addSelect('d')
            ->andWhere('c.fecha >= :desde AND c.fecha < :hasta')
            ->andWhere('d.tipoDocumento IN (:tipos)')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->setParameter('tipos', ['ticket', 'ticket_rectificativo'])
            ->orderBy('c.fecha', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->agruparImportesPorMes($cobros);
    }

    /** @param DocumentoCobro[] $cobros */
    private function agruparImportesPorMes(array $cobros): array
    {
        $totales = [];
        foreach ($cobros as $cobro) {
            $fecha = $cobro->getFecha();
            if ($fecha === null) {
                continue;
            }
            $mes = (int) $fecha->format('n');
            $totales[$mes] = ($totales[$mes] ?? 0.0) + (float) $cobro->getImporteBruto();
        }

        $resultado = [];
        foreach ($totales as $mes => $importe) {
            $resultado[] = ['mes' => $mes, 'importe' => $importe];
        }

        return $resultado;
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

    public function countPendientesConciliacion(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.banco IS NULL')
            ->andWhere('LOWER(c.metodo) != :efectivo')
            ->setParameter('efectivo', 'efectivo')
            ->getQuery()->getSingleScalarResult();
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

    public function findCandidatosParaBanco(Banco $banco, int $limite = 10): array
    {
        $fecha = $banco->getFechaBn();
        $importe = (float) $banco->getImporteBn();
        if (!$fecha || $importe <= 0) return [];
        $desde = (clone $fecha)->modify('-30 days');
        $hasta = (clone $fecha)->modify('+15 days');
        $cobros = $this->createQueryBuilder('c')
            ->join('c.documento', 'd')->addSelect('d')
            ->andWhere('c.banco IS NULL')->andWhere('LOWER(c.metodo) != :efectivo')
            ->andWhere('c.fecha BETWEEN :desde AND :hasta')
            ->setParameter('efectivo', 'efectivo')->setParameter('desde', $desde)->setParameter('hasta', $hasta)
            ->orderBy('c.fecha', 'DESC')->setMaxResults(100)->getQuery()->getResult();
        $resultados = [];
        foreach ($cobros as $cobro) {
            $bruto = (float) $cobro->getImporteBruto();
            $neto = (float) $cobro->getImporteNeto();
            $difBruto = abs($importe - $bruto);
            $difNeto = abs($importe - $neto);
            $dif = min($difBruto, $difNeto);
            if ($dif > 25) continue;
            $dias = abs($fecha->diff($cobro->getFecha())->days);
            $score = $dif < .01 ? 70 : ($dif <= 1 ? 55 : ($dif <= 5 ? 40 : 20));
            $score += $dias <= 3 ? 25 : ($dias <= 7 ? 15 : ($dias <= 15 ? 10 : 0));
            if ($difNeto < .01 && in_array($cobro->getMetodo(), ['tarjeta', 'financiacion'], true)) $score += 10;
            if ($score < 30) continue;
            $resultados[] = [
                'id' => $cobro->getId(), 'fecha' => $cobro->getFecha()?->format('Y-m-d'),
                'concepto' => 'Documento ' . ($cobro->getDocumento()?->getNumeroFormateado() ?? $cobro->getDocumento()?->getId()),
                'importe' => $difNeto < $difBruto ? $neto : $bruto, 'metodo' => $cobro->getMetodo(),
                'diferenciaDias' => $dias, 'score' => min($score, 100),
                'motivo' => $difNeto < .01 ? 'Importe neto exacto' : 'Importe bruto compatible',
                'origen' => 'documento',
            ];
        }
        usort($resultados, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($resultados, 0, $limite);
    }
}
