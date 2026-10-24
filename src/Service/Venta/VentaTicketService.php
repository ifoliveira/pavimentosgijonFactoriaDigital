<?php

namespace App\Service\Venta;

use App\Entity\Cestas;
use App\Repository\TiposmovimientoRepository;
use App\Service\PagoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class VentaTicketService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiposmovimientoRepository $tiposmovimientoRepository,
        private PagoService $pagoService,
    ) {}

    /**
     * Solo admite cobros pendientes de tickets históricos ya emitidos.
     * Las nuevas ventas se registran exclusivamente como Documentos desde el TPV.
     */
    public function finalizarVentaManual(
        Cestas $cesta,
        string $tipoPago,
        string $numeroTicket,
        float $importeTotal,
        float $importeSenal = 0.0,
        ?\DateTimeInterface $fecha = null
    ): Cestas {
        if ($cesta->getEstadoCs() !== 3 || !$cesta->getNumticketCs()) {
            throw new GoneHttpException('La creación de tickets antiguos está deshabilitada. Utiliza el TPV.');
        }

        $fecha ??= new \DateTime('now', new \DateTimeZone('Europe/Madrid'));
        $pagado = 0.0;
        foreach ($cesta->getPagos() as $pago) {
            $pagado += (float) $pago->getImportePg();
        }
        $pendiente = round($cesta->getImporteTotCs() - $pagado, 2);
        $importePago = $importeSenal == 0.0 ? $pendiente : $importeSenal;
        if (!is_finite($importePago) || $importePago <= 0 || $importePago > $pendiente) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Importe de cobro no válido.');
        }

        if (round($pagado + $importePago, 2) === round($cesta->getImporteTotCs(), 2)) {
            $cesta->setEstadoCs(2);
            $cesta->setFechaFinCs($fecha);
        }

        $cesta->setTimestampCs(new \DateTime('now', new \DateTimeZone('Europe/Madrid')));
        $this->em->persist($cesta);
        $this->pagoService->ticketPagoFinal($cesta, $importePago, $tipoPago, $this->tiposmovimientoRepository);

        return $cesta;
    }
}
