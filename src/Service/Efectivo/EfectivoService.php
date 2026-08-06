<?php

namespace App\Service\Efectivo;

use App\Entity\Efectivo;
use Doctrine\ORM\EntityManagerInterface;

class EfectivoService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function registrarSalida(
        string $importe,
        string $concepto,
        ?\DateTimeInterface $fecha = null
    ): Efectivo {

        $movimiento = new Efectivo();

        $movimiento->setFechaEf(
            $fecha ?? new \DateTime()
        );

        $movimiento->setConceptoEf($concepto);

        /*
         * Yo guardaría las salidas en negativo.
         */
        $movimiento->setImporteEf(
            number_format(
                -abs((float) $importe),
                2,
                '.',
                ''
            )
        );

        /*$movimiento->setTipo('salida');*/

        $this->em->persist($movimiento);

        return $movimiento;
    }
}