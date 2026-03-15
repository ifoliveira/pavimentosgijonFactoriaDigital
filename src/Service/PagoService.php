<?php
namespace App\Service;

use App\Entity\Cestas;
use App\Entity\Efectivo;
use App\Entity\Pagos;
use App\Repository\BancoRepository;
use App\Repository\TiposmovimientoRepository;
use Doctrine\ORM\EntityManagerInterface;

class PagoService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Crea un pago y opcionalmente un movimiento de efectivo.
     * No hace flush — el controlador es responsable de llamarlo.
     */
    public function ticketPagoFinal(
        Cestas $cesta,
        float $importe,
        string $tipopago,
        TiposmovimientoRepository $tiposmovimientoRepository
    ): Pagos {
        $efectivo = null;

        if ($tipopago === 'Efectivo') {
            $tipomovimiento = $tiposmovimientoRepository->findOneBy(['descripcionTm' => 'Ventas']);

            $efectivo = new Efectivo();
            $efectivo->setTipoEf($tipomovimiento);
            $efectivo->setConceptoEf('Ventas en efectivo');
            $efectivo->setFechaEf(new \DateTime());
            $efectivo->setImporteEf($importe);

            $this->em->persist($efectivo);
        }

        $pago = new Pagos();
        $pago->setCesta($cesta);
        $pago->setFechaPg(new \DateTime());
        $pago->setImportePg($importe);
        $pago->setTipoPg($tipopago);
        $pago->setEfectivoPg($efectivo);

        $this->em->persist($pago);

        return $pago;
    }

    /**
     * Concilia un pago con un movimiento bancario.
     * No hace flush — el controlador es responsable de llamarlo.
     */
    public function conciliar(Pagos $pago, int $bancoId, BancoRepository $bancoRepository): Pagos
    {
        $banco = $bancoRepository->find($bancoId);

        if ($pago->getImportePg() != $banco->getImporteBn()) {
            $banconuevo = clone $banco;
            $banconuevo->setImporteBn($pago->getImportePg());
            $banconuevo->setConciliado(true);
            $banco->setImporteBn($banco->getImporteBn() - $pago->getImportePg());
            $pago->setBancoPg($banconuevo);
            $this->em->persist($banconuevo);
        } else {
            $pago->setBancoPg($banco);
            $banco->setConciliado(true);
        }

        $this->em->persist($banco);

        return $pago;
    }
}