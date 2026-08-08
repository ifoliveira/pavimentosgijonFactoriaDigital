<?php

namespace App\Repository;

use App\Entity\ProyectoCobro;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Banco;

class ProyectoCobroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProyectoCobro::class);
    }

    public function findCandidatosParaBanco(
        Banco $banco,
        int $limite = 10
    ): array {

        $fechaBanco = $banco->getFechaBn();
        $importeBanco = (float) $banco->getImporteBn();

        if ($fechaBanco === null || $importeBanco <= 0) {
            return [];
        }

        /*
        * Un cobro puede aparecer en banco varios días después
        * de haber sido registrado.
        */
        $desde = (clone $fechaBanco)->modify('-30 days');
        $hasta = (clone $fechaBanco)->modify('+15 days');

        /*
        * Recuperamos solo cobros que:
        *
        * - todavía no tienen movimiento Banco
        * - no son efectivo
        * - están razonablemente cerca en fecha
        */
        $cobros = $this->createQueryBuilder('c')
            ->leftJoin('c.proyecto', 'p')
            ->addSelect('p')
            ->andWhere('c.banco IS NULL')
            ->andWhere('c.metodo != :efectivo')
            ->andWhere('c.fecha BETWEEN :desde AND :hasta')
            ->setParameter('efectivo', 'efectivo')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('c.fecha', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $candidatos = [];


        foreach ($cobros as $cobro) {

            $bruto = (float) $cobro->getImporteBruto();
            $neto = (float) $cobro->getImporteNeto();

            /*
            * El banco puede coincidir con bruto o neto.
            *
            * Transferencia/Bizum normalmente bruto.
            * Tarjeta/financiación pueden coincidir con neto.
            */
            $diferenciaBruto = abs(
                $importeBanco - $bruto
            );

            $diferenciaNeto = abs(
                $importeBanco - $neto
            );

            $diferenciaImporte = min(
                $diferenciaBruto,
                $diferenciaNeto
            );

            /*
            * Si se aleja demasiado, ni siquiera lo proponemos.
            */
            if ($diferenciaImporte > 25) {
                continue;
            }

            $diferenciaDias = abs(
                $fechaBanco
                    ->diff($cobro->getFecha())
                    ->days
            );

            $score = 0;
            $motivos = [];

            /*
            * ========================================================
            * IMPORTE
            * ========================================================
            */
            if ($diferenciaBruto < 0.01) {

                $score += 70;
                $motivos[] = 'Importe bruto exacto';

            } elseif ($diferenciaNeto < 0.01) {

                $score += 70;
                $motivos[] = 'Importe neto exacto';

            } elseif ($diferenciaImporte <= 1) {

                $score += 55;
                $motivos[] = 'Importe prácticamente idéntico';

            } elseif ($diferenciaImporte <= 5) {

                $score += 40;
                $motivos[] = 'Importe muy próximo';

            } else {

                $score += 20;
                $motivos[] = 'Importe próximo';
            }

            /*
            * ========================================================
            * FECHA
            * ========================================================
            */
            if ($diferenciaDias <= 2) {

                $score += 20;
                $motivos[] = 'Fecha muy próxima';

            } elseif ($diferenciaDias <= 5) {

                $score += 15;
                $motivos[] = 'Fecha próxima';

            } elseif ($diferenciaDias <= 10) {

                $score += 10;

            } elseif ($diferenciaDias <= 20) {

                $score += 5;
            }

            /*
            * ========================================================
            * MÉTODO
            * ========================================================
            *
            * Si coincide por neto y es tarjeta/financiación,
            * tiene bastante sentido.
            */
            if (
                $diferenciaNeto < 0.01
                && in_array(
                    $cobro->getMetodo(),
                    ['tarjeta', 'financiacion'],
                    true
                )
            ) {
                $score += 10;
                $motivos[] = 'Neto compatible con método de cobro';
            }

            /*
            * Si coincide por bruto y es transferencia/Bizum,
            * también tiene sentido.
            */
            if (
                $diferenciaBruto < 0.01
                && in_array(
                    $cobro->getMetodo(),
                    ['transferencia', 'bizum'],
                    true
                )
            ) {
                $score += 10;
                $motivos[] = 'Importe compatible con método de cobro';
            }

            /*
            * ========================================================
            * CONCEPTO / PROYECTO
            * ========================================================
            *
            * Intentamos comparar el concepto bancario con:
            * - nombre del proyecto
            * - referencia del cobro
            */
            $textoBanco = $this->normalizarTexto(
                $banco->getConceptoBn() ?? ''
            );

            $textoCobro =
                ($cobro->getProyecto()?->getNombre() ?? '')
                . ' '
                . ($cobro->getReferencia() ?? '');

            $textoCobro = $this->normalizarTexto(
                $textoCobro
            );

            if ($textoBanco !== '' && $textoCobro !== '') {

                similar_text(
                    $textoBanco,
                    $textoCobro,
                    $similitud
                );

                if ($similitud >= 60) {

                    $score += 10;
                    $motivos[] = 'Proyecto/referencia similar';

                } elseif ($similitud >= 35) {

                    $score += 5;
                }
            }

            if ($score < 30) {
                continue;
            }

            /*
            * Para el Twig mantenemos el mismo contrato que Forecast:
            *
            * id
            * fecha
            * concepto
            * importe
            * diferenciaDias
            * score
            * motivo
            *
            * Así pintarCandidato() sirve para ambos.
            */
            $candidatos[] = [
                'id' => $cobro->getId(),

                'fecha' =>
                    $cobro->getFecha()?->format('Y-m-d'),

                'concepto' =>
                    $cobro->getProyecto()?->getNombre()
                    ?? 'Cobro proyecto',

                /*
                * Mostramos como importe el que realmente
                * esperamos encontrar en Banco.
                */
                'importe' =>
                    $diferenciaNeto < $diferenciaBruto
                        ? $neto
                        : $bruto,

                'importeBruto' => $bruto,
                'importeNeto' => $neto,

                'metodo' =>
                    $cobro->getMetodo(),

                'referencia' =>
                    $cobro->getReferencia(),

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
        * Mejores coincidencias primero.
        */
        usort(
            $candidatos,
            static function (array $a, array $b): int {

                if ($a['score'] === $b['score']) {

                    if (
                        $a['diferenciaImporte']
                        === $b['diferenciaImporte']
                    ) {
                        return $a['diferenciaDias']
                            <=> $b['diferenciaDias'];
                    }

                    return $a['diferenciaImporte']
                        <=> $b['diferenciaImporte'];
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

}