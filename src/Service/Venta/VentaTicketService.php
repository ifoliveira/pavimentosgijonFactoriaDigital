<?php

namespace App\Service\Venta;

use App\Entity\Cestas;
use App\Entity\Detallecesta;
use App\Entity\Productos;
use App\Repository\CestasRepository;
use App\Repository\ProductosRepository;
use App\Repository\TiposmovimientoRepository;
use App\Service\PagoService;
use Doctrine\ORM\EntityManagerInterface;

class VentaTicketService
{
    private const ESTADO_ABIERTA = 1;
    private const ESTADO_FINALIZADA = 2;
    private const ESTADO_PENDIENTE_COBRO = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private CestasRepository $cestasRepository,
        private ProductosRepository $productosRepository,
        private TiposmovimientoRepository $tiposmovimientoRepository,
        private PagoService $pagoService,
    ) {}

    public function finalizarVentaManual(
        Cestas $cesta,
        string $tipoPago,
        string $numeroTicket,
        float $importeTotal,
        float $importeSenal = 0.0,
        ?\DateTimeInterface $fecha = null
    ): Cestas {
        $fecha ??= new \DateTime('now', new \DateTimeZone('Europe/Madrid'));
        $pagado = $importeSenal;

        if ($cesta->getEstadoCs() === self::ESTADO_ABIERTA) {
            if ($importeSenal == 0.0) {
                $cesta->setEstadoCs(self::ESTADO_FINALIZADA);
                $cesta->setFechaFinCs($fecha);
            } else {
                $cesta->setEstadoCs(self::ESTADO_PENDIENTE_COBRO);
            }

            $cesta->setImporteTotCs($importeTotal);
            $cesta->setTipopagoCs($tipoPago);
            $cesta->setNumticketCs($numeroTicket);
            $cesta->setFechaCs($fecha);
        } else {
            foreach ($cesta->getPagos() as $pago) {
                $pagado += (float) $pago->getImportePg();
            }

            if ($pagado == $cesta->getImporteTotCs()) {
                $cesta->setEstadoCs(self::ESTADO_FINALIZADA);
                $cesta->setFechaFinCs($fecha);
            }
        }

        $cesta->setTimestampCs(new \DateTime('now', new \DateTimeZone('Europe/Madrid')));
        $this->em->persist($cesta);

        $importePago = $importeSenal == 0.0 ? $importeTotal : $importeSenal;
        $this->pagoService->ticketPagoFinal($cesta, $importePago, $tipoPago, $this->tiposmovimientoRepository);

        return $cesta;
    }

    public function crearVentaTpv(VentaTpvInput $input): Cestas
    {
        $existente = $this->cestasRepository->findOneBy(['tpvUuid' => $input->uuid]);
        if ($existente instanceof Cestas) {
            return $existente;
        }

        $this->em->beginTransaction();

        try {
            $existente = $this->cestasRepository->findOneBy(['tpvUuid' => $input->uuid]);
            if ($existente instanceof Cestas) {
                $this->em->commit();
                return $existente;
            }

            $cesta = new Cestas();
            $cesta->setTpvUuid($input->uuid);
            $cesta->setTpvNumeroTicketLocal($input->numeroTicketLocal);
            $cesta->setTpvCaja($input->caja);
            $cesta->setFechaCs($input->fecha);
            $cesta->setNumticketCs($this->generarNumeroTicket($input->fecha));
            $cesta->setTipopagoCs($input->formaPago);
            $cesta->setEstadoCs(self::ESTADO_ABIERTA);

            $total = 0.0;
            foreach ($input->lineas as $lineaInput) {
                $detalle = $this->crearDetalle($cesta, $lineaInput);
                $total += $this->calcularTotalLinea($detalle);
                $this->em->persist($detalle);
            }

            $total = round($total, 2);
            if ($input->totalInformado !== null && abs($total - $input->totalInformado) > 0.01) {
                throw new \InvalidArgumentException(sprintf(
                    'El total informado %.2f no coincide con el total calculado %.2f.',
                    $input->totalInformado,
                    $total
                ));
            }

            $this->finalizarVentaManual(
                $cesta,
                $input->formaPago,
                $cesta->getNumticketCs(),
                $total,
                0.0,
                $input->fecha
            );

            $this->em->flush();
            $this->em->commit();

            return $cesta;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function calcularTotalCesta(Cestas $cesta): float
    {
        $total = 0.0;

        foreach ($cesta->getDetallecesta() as $detalle) {
            $total += $this->calcularTotalLinea($detalle);
        }

        return round($total, 2);
    }

    public function generarNumeroTicket(?\DateTimeInterface $fecha = null): string
    {
        $fecha ??= new \DateTime('now', new \DateTimeZone('Europe/Madrid'));

        return $fecha->format('y') . $fecha->format('z') . $fecha->format('His') . '.pdf';
    }

    private function crearDetalle(Cestas $cesta, VentaLineaInput $lineaInput): Detallecesta
    {
        $producto = $this->productosRepository->find($lineaInput->productoId);
        if (!$producto instanceof Productos) {
            throw new \InvalidArgumentException(sprintf('Producto no encontrado: %d.', $lineaInput->productoId));
        }

        $detalle = new Detallecesta();
        $detalle->setCestaDc($cesta);
        $detalle->setProductoDc($producto);
        $detalle->setCantidadDc($lineaInput->cantidad);
        $detalle->setPvpDc($lineaInput->precioAplicado);
        $detalle->setPrecioDc($lineaInput->coste ?? (float) $producto->getPrecioPd());
        $detalle->setDescuentoDc($lineaInput->descuento);
        $detalle->setTextoDc($lineaInput->texto);
        $detalle->setTpvPrecioOriginal($lineaInput->precioOriginal);
        $detalle->setTpvTipoIva($lineaInput->tipoIva);

        return $detalle;
    }

    private function calcularTotalLinea(Detallecesta $detalle): float
    {
        $pvp = (float) $detalle->getPvpDc();
        $descuento = (float) $detalle->getDescuentoDc();

        return (float) $detalle->getCantidadDc() * ($pvp - ($pvp * $descuento / 100));
    }
}
