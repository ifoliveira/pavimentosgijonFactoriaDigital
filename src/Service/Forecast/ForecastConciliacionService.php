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

        // Una previsión de venta es un ingreso; una de pago, una salida.
        // Ambas partes deben tener el mismo sentido para evitar conciliaciones erróneas.
        if ((float) $forecast->getImporteFr() * (float) $banco->getImporteBn() <= 0) {
            throw new \LogicException(
                'El Forecast y el movimiento bancario deben tener el mismo sentido.'
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
