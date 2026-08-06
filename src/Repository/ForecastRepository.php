<?php

namespace App\Repository;

use App\Entity\Forecast;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Banco;


/**
 * @method Forecast|null find($id, $lockMode = null, $lockVersion = null)
 * @method Forecast|null findOneBy(array $criteria, array $orderBy = null)
 * @method Forecast[]    findAll()
 * @method Forecast[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForecastRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forecast::class);
    }

    public function findCandidatosParaBanco(
        Banco $banco,
        int $limite = 10
    ): array {

        $fechaBanco = $banco->getFechaBn();
        $importeBanco = (float) $banco->getImporteBn();

        if ($fechaBanco === null) {
            return [];
        }

        /*
        * Buscamos en una ventana suficientemente amplia.
        *
        * Una previsión puede haberse pagado antes o después
        * de la fecha prevista.
        */
        $desde = (clone $fechaBanco)->modify('-45 days');
        $hasta = (clone $fechaBanco)->modify('+45 days');

        /*
        * Primero recuperamos candidatos razonables.
        *
        * No hacemos todavía todo el score en SQL porque
        * será más fácil evolucionarlo después.
        */
        $forecast = $this->createQueryBuilder('f')
            ->andWhere('f.estadoFr = :estado')
            ->andWhere('f.fechaFr BETWEEN :desde AND :hasta')
            ->setParameter('estado', 'P')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('f.fechaFr', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $candidatos = [];

        foreach ($forecast as $pago) {

            $importeForecast = (float) $pago->getImporteFr();

            $diferenciaImporte = abs(
                $importeBanco - $importeForecast
            );

            $diferenciaDias = abs(
                $fechaBanco->diff($pago->getFechaFr())->days
            );

            /*
            * ---------------------------------------------------------
            * FILTRO
            * ---------------------------------------------------------
            *
            * Si el importe se diferencia demasiado, directamente
            * no nos interesa como candidato automático.
            */
            if ($diferenciaImporte > 25) {
                continue;
            }

            /*
            * ---------------------------------------------------------
            * SCORE
            * ---------------------------------------------------------
            */
            $score = 0;
            $motivos = [];

            /*
            * IMPORTE
            */
            if ($diferenciaImporte < 0.01) {

                $score += 70;
                $motivos[] = 'Importe exacto';

            } elseif ($diferenciaImporte <= 1) {

                $score += 55;
                $motivos[] = 'Importe prácticamente idéntico';

            } elseif ($diferenciaImporte <= 5) {

                $score += 40;
                $motivos[] = 'Importe muy próximo';

            } elseif ($diferenciaImporte <= 25) {

                $score += 20;
                $motivos[] = 'Importe próximo';
            }

            /*
            * FECHA
            */
            if ($diferenciaDias <= 3) {

                $score += 25;
                $motivos[] = 'Fecha muy próxima';

            } elseif ($diferenciaDias <= 7) {

                $score += 20;
                $motivos[] = 'Fecha próxima';

            } elseif ($diferenciaDias <= 15) {

                $score += 10;

            } elseif ($diferenciaDias <= 30) {

                $score += 5;
            }

            /*
            * CONCEPTO
            *
            * De momento hacemos una comprobación muy sencilla.
            * Luego podemos mejorar proveedor/nombre/referencia.
            */
            $conceptoBanco = $this->normalizarTexto(
                $banco->getConceptoBn() ?? ''
            );

            $conceptoForecast = $this->normalizarTexto(
                $pago->getConceptoFr() ?? ''
            );

            if (
                $conceptoBanco !== ''
                && $conceptoForecast !== ''
            ) {

                similar_text(
                    $conceptoBanco,
                    $conceptoForecast,
                    $porcentajeSimilitud
                );

                if ($porcentajeSimilitud >= 70) {

                    $score += 15;
                    $motivos[] = 'Concepto similar';

                } elseif ($porcentajeSimilitud >= 40) {

                    $score += 5;
                }
            }

            /*
            * No devolvemos candidatos demasiado pobres.
            */
            if ($score < 30) {
                continue;
            }

            $candidatos[] = [
                'id' => $pago->getId(),

                'fecha' =>
                    $pago->getFechaFr()?->format('Y-m-d'),

                'concepto' =>
                    $pago->getConceptoFr(),

                'importe' =>
                    (float) $pago->getImporteFr(),

                'diferenciaDias' =>
                    $diferenciaDias,

                'diferenciaImporte' =>
                    round($diferenciaImporte, 2),

                'score' =>
                    min($score, 100),

                'motivo' =>
                    implode(' · ', $motivos),
            ];
        }

        /*
        * Los mejores candidatos primero.
        */
        usort(
            $candidatos,
            static function (array $a, array $b): int {

                if ($a['score'] === $b['score']) {

                    /*
                    * Si tienen el mismo score,
                    * priorizamos menor diferencia temporal.
                    */
                    return $a['diferenciaDias']
                        <=> $b['diferenciaDias'];
                }

                return $b['score']
                    <=> $a['score'];
            }
        );

        return array_slice(
            $candidatos,
            0,
            $limite
        );
    }

    public function buscarPendientesConciliacion(
        string $texto,
        int $limite = 20
    ): array {

        $qb = $this->createQueryBuilder('f');

        $qb
            ->andWhere('f.estadoFr = :estado')
            ->setParameter('estado', 'P');

        /*
        * Si parece un importe:
        * "1250", "-1250", "1250.50"...
        */
        $importe = str_replace(',', '.', $texto);

        if (is_numeric($importe)) {

            $qb
                ->andWhere('ABS(f.importeFr - :importe) < 0.01')
                ->orWhere('ABS(f.importeFr + :importe) < 0.01')
                ->setParameter('importe', (float) $importe);

        } else {

            /*
            * Si no es importe, buscamos por concepto.
            */
            $qb
                ->andWhere('LOWER(f.conceptoFr) LIKE :texto')
                ->setParameter(
                    'texto',
                    '%' . mb_strtolower($texto) . '%'
                );
        }

        $forecast = $qb
            ->orderBy('f.fechaFr', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();

        return array_map(
            static function (Forecast $f): array {

                return [
                    'id' => $f->getId(),
                    'fecha' => $f->getFechaFr()?->format('Y-m-d'),
                    'concepto' => $f->getConceptoFr(),
                    'importe' => (float) $f->getImporteFr(),
                ];

            },
            $forecast
        );
    }    
        
    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(
            trim($texto),
            'UTF-8'
        );

        $texto = strtr($texto, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        $texto = preg_replace(
            '/[^a-z0-9\s]/',
            ' ',
            $texto
        );

        $texto = preg_replace(
            '/\s+/',
            ' ',
            $texto
        );

        return trim($texto);
    }

    // /**
    //  * @return Forecast[] Returns an array of Forecast objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Forecast
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
