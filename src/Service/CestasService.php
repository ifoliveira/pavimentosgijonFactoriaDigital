<?php
namespace App\Service;

use App\Repository\CestasRepository;

class CestasService
{
    public function __construct(
        private CestasRepository $cestasRepository,
    ) {}

    /**
     * Devuelve el total de ventas y el total pagado
     * de los tickets en estado 3 (semana/pendientes).
     */
    public function getTotalesTicketsSnal(): array
    {
        $ventas = 0.0;
        $pagos  = 0.0;

        foreach ($this->cestasRepository->findPendientesCobro() as $ticket) {
            $ventas += $ticket->getImporteTotCs();
            $pagos  += $ticket->getTotalPagos(); // ya existe en la entidad
        }

        return [
            'ventas'    => $ventas,
            'pagos'     => $pagos,
            'pendiente' => $ventas - $pagos,
        ];
    }
}