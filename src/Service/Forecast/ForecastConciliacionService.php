<?php

namespace App\Service\Forecast;

use App\Entity\Banco;
use App\Entity\Forecast;
use Doctrine\ORM\EntityManagerInterface;

class ForecastConciliacionService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function conciliar(
        Forecast $forecast,
        Banco $banco
    ): void {

        if ($forecast->getEstadoFr() !== 'P') {
            throw new \LogicException(
                'El Forecast ya no está pendiente.'
            );
        }

        if ((bool) $banco->isConciliado()) {
            throw new \LogicException(
                'El movimiento bancario ya está conciliado.'
            );
        }

        /*
         * Protección importante:
         * salida con salida.
         */
        if (
            (float) $forecast->getImporteFr() >= 0 ||
            (float) $banco->getImporteBn() >= 0
        ) {
            throw new \LogicException(
                'Este conciliador solo admite pagos/salidas.'
            );
        }

        /*
         * Aquí adaptamos los setters a tu modelo real.
         */
        $forecast->setEstadoFr('C');
        $forecast->setBanco($banco);

        $banco->setConciliado(true);

        /*
         * Si Banco tiene relación directa con Forecast:
         *
         * $banco->setForecast($forecast);
         *
         * o:
         *
         * $forecast->setBanco($banco);
         *
         * dependerá de tus entidades actuales.
         */

        $this->em->flush();
    }
}