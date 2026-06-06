<?php

namespace App\Service\Documento;


use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\ManoObra;
use App\Repository\DocumentoRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Documento\SerieService;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Proyecto\ProyectoCalculatorService;
use App\Service\Proyecto\ProyectoService;
use App\Service\Documento\DocumentoEstadoService;
use App\Service\Stock\StockReservaService;

class DocumentoEstadoService
{
    public function __construct(
        private DocumentoRepository $documentoRepository,
        private EntityManagerInterface $em,
        private SerieService $serieService,
        private DocumentoCalculatorService $documentoCalculatorService,
        private ProyectoCalculatorService $proyectoCalculatorService,
        private StockReservaService $stockReservaService,
    ) {
    }

    public function marcarComoEntregado(int $documentoId): void
    {
        $documento = $this->getDocumento($documentoId);

        if ($documento->getEstadoComercial() !== 'borrador') {
            throw new \RuntimeException('Solo se puede entregar un documento en borrador.');
        }

        $documento->setEstadoComercial('entregado');

        $this->em->flush();
    }

    public function aceptar(int $documentoId): void
    {
        $documento = $this->getDocumento($documentoId);

        if ($documento->getEstadoComercial() !== 'entregado') {
            throw new \RuntimeException('Solo se puede aceptar un documento entregado.');
        }

        if (!$documento->getCliente()) {
            throw new \RuntimeException('No se puede aceptar un documento sin cliente.');
        }

        if ($documento->getLineas()->isEmpty()) {
            throw new \RuntimeException('No se puede aceptar un documento sin líneas.');
        }

        $documento->setEstadoComercial('aceptado');
        $documento->setFechaAceptacion(new \DateTime());

        $this->em->flush();
    }

    public function rechazar(int $documentoId): void
    {
        $documento = $this->getDocumento($documentoId);

        if ($documento->getEstadoComercial() !== 'entregado') {
            throw new \RuntimeException('Solo se puede rechazar un documento entregado.');
        }

        $documento->setEstadoComercial('rechazado');


        // Libera las reservas activas asociadas a este presupuesto/documento.
        $this->stockReservaService->liberarReservasDocumento($documento);

        $this->em->flush();
    }

    private function getDocumento(int $id): Documento
    {
        $documento = $this->documentoRepository->find($id);

        if (!$documento) {
            throw new \RuntimeException('Documento no encontrado.');
        }

        return $documento;
    }

        public function aceptarYGenerarFactura(int $documentoId): Documento
    {
        $presupuesto = $this->getDocumento($documentoId);

        if ($presupuesto->getTipoDocumento() !== 'presupuesto') {
            throw new \RuntimeException('Solo se puede aceptar y convertir un presupuesto.');
        }

        if ($presupuesto->getEstadoComercial() !== 'entregado') {
            throw new \RuntimeException('Solo se puede aceptar un documento entregado.');
        }

        if (!$presupuesto->getCliente()) {
            throw new \RuntimeException('No se puede aceptar un documento sin cliente.');
        }

        if (!$presupuesto->getProyecto()) {
            throw new \RuntimeException('No se puede aceptar un presupuesto sin proyecto.');
        }

        if ($presupuesto->getLineas()->isEmpty()) {
            throw new \RuntimeException('No se puede aceptar un documento sin líneas.');
        }

        foreach ($presupuesto->getLineas() as $linea) {
            if ($linea->isPendienteClasificarFacturacion()) {
                throw new \RuntimeException('Hay líneas pendientes de clasificar para facturación.');
            }
        }

        $lineasFactura = [];
        $lineasTicket = [];

        foreach ($presupuesto->getLineas() as $lineaOrigen) {
            if ($lineaOrigen->isFacturaObra()) {
                $lineasFactura[] = $lineaOrigen;
            }

            if ($lineaOrigen->isTicketTienda()) {
                $lineasTicket[] = $lineaOrigen;
            }
        }

        if (count($lineasFactura) === 0 && count($lineasTicket) === 0) {
            throw new \RuntimeException('No hay líneas para generar factura ni ticket.');
        }

        $factura = null;
        $ticket = null;

        if (count($lineasFactura) > 0) {
            $factura = $this->crearDocumentoDesdeLineas(
                presupuestoOrigen: $presupuesto,
                tipoDocumento: 'factura',
                lineasOrigen: $lineasFactura
            );

            $this->copiarManoObra($presupuesto, $factura);
        }

        if (count($lineasTicket) > 0) {
            $ticket = $this->crearDocumentoDesdeLineas(
                presupuestoOrigen: $presupuesto,
                tipoDocumento: 'ticket',
                lineasOrigen: $lineasTicket
            );
        }

        $presupuesto->setEstadoComercial('convertido');
        $presupuesto->setFechaAceptacion(new \DateTime());

        if ($factura) {
            $presupuesto->setFacturaVinculada($factura);
        }


        // Consume las reservas del presupuesto original.
        // Esto crea StockMovimiento de salida y marca reservas como consumidas.
        $this->stockReservaService->consumirReservasDocumento($presupuesto);        

        $proyecto = $presupuesto->getProyecto();
        $this->em->flush();

        $this->proyectoCalculatorService->recalcularProyecto($proyecto, false);

        return $factura ?? $ticket ?? $presupuesto;
    }

    private function crearDocumentoDesdeLineas(
        Documento $presupuestoOrigen,
        string $tipoDocumento,
        array $lineasOrigen
    ): Documento {
        $documentoNuevo = new Documento();

        $documentoNuevo->setTipoDocumento($tipoDocumento);
        $documentoNuevo->setCliente($presupuestoOrigen->getCliente());
        $documentoNuevo->setProyecto($presupuestoOrigen->getProyecto());
        $documentoNuevo->setNotas($presupuestoOrigen->getNotas());
        $documentoNuevo->setEstadoComercial('borrador');
        $documentoNuevo->setEstadoCobro('no_aplica');
        $documentoNuevo->setEstadoEjecucion('pendiente');
        $documentoNuevo->setFechaEmision(new \DateTime());

        $this->serieService->asignarNumeracion($documentoNuevo);

        $this->em->persist($documentoNuevo);

        $posicion = 1;

        foreach ($lineasOrigen as $lineaOrigen) {
            $lineaNueva = $this->copiarLineaDocumento($lineaOrigen);
            $lineaNueva->setPosicion($posicion);

            $documentoNuevo->addLinea($lineaNueva);

            $posicion++;
        }

        $this->recalcularTotalesDocumento($documentoNuevo);

        return $documentoNuevo;
    }
    private function copiarLineaDocumento(DocumentoLinea $lineaOrigen): DocumentoLinea
    {
        $lineaNueva = new DocumentoLinea();

        $lineaNueva->setTipoLinea($lineaOrigen->getTipoLinea());
        $lineaNueva->setProducto($lineaOrigen->getProducto());
        $lineaNueva->setDescripcion($lineaOrigen->getDescripcion());
        $lineaNueva->setCantidad($lineaOrigen->getCantidad());
        $lineaNueva->setUnidad($lineaOrigen->getUnidad());
        $lineaNueva->setPrecioUnitario($lineaOrigen->getPrecioUnitario());
        $lineaNueva->setCosteUnitario($lineaOrigen->getCosteUnitario());
        $lineaNueva->setDescuento($lineaOrigen->getDescuento());
        $lineaNueva->setTipoIva($lineaOrigen->getTipoIva());
        $lineaNueva->setAfectaStock(false);
        $lineaNueva->setStockMovido(false);
        $lineaNueva->setOrigenLinea($lineaOrigen->getOrigenLinea());
        $lineaNueva->setCatalogoProducto($lineaOrigen->getCatalogoProducto());
        $lineaNueva->setDestinoFacturacion($lineaOrigen->getDestinoFacturacion());

        $lineaNueva->setSubtotal($lineaOrigen->getSubtotal());
        $lineaNueva->setTotalIva($lineaOrigen->getTotalIva());
        $lineaNueva->setTotalCoste($lineaOrigen->getTotalCoste());

        return $lineaNueva;
    }

    private function recalcularTotalesDocumento(Documento $documento): void
    {
        $baseImponible = 0.0;
        $totalIva = 0.0;
        $total = 0.0;
        $totalCoste = 0.0;

        foreach ($documento->getLineas() as $linea) {
            $subtotal = (float) $linea->getSubtotal();
            $iva = (float) $linea->getTotalIva();
            $coste = (float) $linea->getTotalCoste();

            $baseImponible += $subtotal;
            $totalIva += $iva;
            $total += $subtotal + $iva;
            $totalCoste += $coste;
        }

        $documento->setBaseImponible(number_format($baseImponible, 2, '.', ''));
        $documento->setTotalIva(number_format($totalIva, 2, '.', ''));
        $documento->setTotal(number_format($total, 2, '.', ''));
        $documento->setTotalCoste(number_format($totalCoste, 2, '.', ''));
    }

    private function copiarManoObra(Documento $presupuestoOrigen, Documento $factura): void
    {
        foreach ($presupuestoOrigen->getManoObra() as $manoObraOrigen) {
            $lineaManoObra = new ManoObra();

            $lineaManoObra->setCategoriaMo($manoObraOrigen->getCategoriaMo());
            $lineaManoObra->setCoste($manoObraOrigen->getCoste());
            $lineaManoObra->setPagado($manoObraOrigen->isPagado());
            $lineaManoObra->setTextoMo($manoObraOrigen->getTextoMo());
            $lineaManoObra->setDocumentoMo($factura);

            $factura->addManoObra($lineaManoObra);
        }
    }
}