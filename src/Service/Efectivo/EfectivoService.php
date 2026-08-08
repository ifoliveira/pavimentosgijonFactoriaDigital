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

    public function registrarEntrada(
        float|string $importe,
        string $concepto,
        ?\DateTimeInterface $fecha = null
    ): Efectivo {

        $importe = (float) $importe;

        if ($importe <= 0) {
            throw new \InvalidArgumentException(
                'El importe de una entrada de efectivo debe ser mayor que cero.'
            );
        }

        $movimiento = new Efectivo();

        $movimiento->setFechaEf(
            $fecha ?? new \DateTime()
        );

        $movimiento->SetTimestampEf(new \DateTime());

        $movimiento->setConceptoEf($concepto);

        $movimiento->setImporteEf(
            number_format($importe, 2, '.', '')
        );

        /*
         * Si tu entidad tiene un campo tipo/origen:
         *
         * $movimiento->setTipo('entrada');
         */

        $this->em->persist($movimiento);

        /*
         * NO hacemos flush aquí.
         *
         * ProyectoCobroService hará el flush después de:
         *
         * - crear ProyectoCobro
         * - crear Efectivo
         * - relacionarlos
         * - recalcular Proyecto
         */

        return $movimiento;
    }    
}