<?php

namespace App\Service\Stock;

use App\Entity\Documento;
use App\Entity\StockMovimiento;
use App\Entity\StockReserva;
use Doctrine\ORM\EntityManagerInterface;

class StockReservaService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function liberarReservasDocumento(Documento $documento): void
    {
        foreach ($documento->getLineas() as $linea) {
            $reserva = $linea->getStockReserva();

            if (!$reserva) {
                continue;
            }

            if ($reserva->getEstado() !== StockReserva::ESTADO_RESERVADA) {
                continue;
            }

            $reserva->liberar();

            $linea->setAfectaStock(false);
            $linea->setStockMovido(false);
        }
    }

    public function consumirReservasDocumento(Documento $documento): void
    {
        foreach ($documento->getLineas() as $linea) {
            $reserva = $linea->getStockReserva();

            if (!$reserva) {
                continue;
            }

            if ($reserva->getEstado() !== StockReserva::ESTADO_RESERVADA) {
                continue;
            }

            if ($linea->isStockMovido()) {
                continue;
            }

            $movimiento = new StockMovimiento();

            $movimiento->setTipoMovimiento(
                $documento->getProyecto()
                    ? StockMovimiento::TIPO_SALIDA_OBRA
                    : StockMovimiento::TIPO_SALIDA_TIENDA
            );

            $movimiento->setProducto($reserva->getProducto());
            $movimiento->setProyecto($documento->getProyecto());
            $movimiento->setDescripcionProducto($reserva->getDescripcionProducto());
            $movimiento->setReferenciaProveedor($reserva->getReferenciaProveedor());
            $movimiento->setCantidad($reserva->getCantidad());
            $movimiento->setPrecioCosteUnitario($reserva->getCosteUnitario());
            $movimiento->setFecha(new \DateTime());

            $movimiento->setObservaciones(
                'Salida de stock desde reserva del documento ' .
                ($documento->getNumeroFormateado() ?: $documento->getId())
            );

            $reserva->consumir();

            $linea->setStockMovido(true);
            $linea->setAfectaStock(true);

            $this->em->persist($movimiento);
        }
    }
}