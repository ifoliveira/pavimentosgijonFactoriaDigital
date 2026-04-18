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

class DocumentoEstadoService
{
    public function __construct(
        private DocumentoRepository $documentoRepository,
        private EntityManagerInterface $em,
        private SerieService $serieService,
        private DocumentoCalculatorService $documentoCalculatorService,
        private ProyectoCalculatorService $proyectoService,
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
        $documento = $this->getDocumento($documentoId);

        if ($documento->getTipoDocumento() !== 'presupuesto') {
            throw new \RuntimeException('Solo se puede aceptar y convertir un presupuesto.');
        }

        if ($documento->getEstadoComercial() !== 'entregado') {
            throw new \RuntimeException('Solo se puede aceptar un documento entregado.');
        }

        if (!$documento->getCliente()) {
            throw new \RuntimeException('No se puede aceptar un documento sin cliente.');
        }

        if ($documento->getLineas()->isEmpty()) {
            throw new \RuntimeException('No se puede aceptar un documento sin líneas.');
        }

        // Crear factura
        $factura = new Documento();
        $factura->setTipoDocumento('factura');
        $factura->setCliente($documento->getCliente());
        $factura->setProyecto($documento->getProyecto());
        $factura->setNotas($documento->getNotas());
        $factura->setEstadoComercial('borrador');
        $factura->setEstadoCobro('pendiente');
        $factura->setEstadoEjecucion('pendiente');
        $factura->setFechaEmision(new \DateTime());

        $this->serieService->asignarNumeracion($factura);

        // Copiar líneas del presupuesto a la factura
        foreach ($documento->getLineas() as $lineaOrigen) {
            $lineaFactura = new DocumentoLinea();
            $lineaFactura->setPosicion($lineaOrigen->getPosicion());
            $lineaFactura->setTipoLinea($lineaOrigen->getTipoLinea());
            $lineaFactura->setProducto($lineaOrigen->getProducto());
            $lineaFactura->setDescripcion($lineaOrigen->getDescripcion());
            $lineaFactura->setCantidad($lineaOrigen->getCantidad());
            $lineaFactura->setUnidad($lineaOrigen->getUnidad());
            $lineaFactura->setPrecioUnitario($lineaOrigen->getPrecioUnitario());
            $lineaFactura->setCosteUnitario($lineaOrigen->getCosteUnitario());
            $lineaFactura->setDescuento($lineaOrigen->getDescuento());
            $lineaFactura->setTipoIva($lineaOrigen->getTipoIva());
            $lineaFactura->setAfectaStock($lineaOrigen->isAfectaStock());
            $lineaFactura->setStockMovido(false);

            $factura->addLinea($lineaFactura);
        }

        // Copiar líneas del presupuesto a la factura
        foreach ($documento->getManoObra() as $manoObraOrigen) {
            $lineaManoObra = new ManoObra();
            $lineaManoObra->setCategoriaMo($manoObraOrigen->getCategoriaMo());
            $lineaManoObra->setCoste($manoObraOrigen->getCoste());
            $lineaManoObra->setPagado($manoObraOrigen->isPagado());
            $lineaManoObra->setTextoMo($manoObraOrigen->getTextoMo());  
            $lineaManoObra->setDocumentoMo($factura);
            $factura->addManoObra($lineaManoObra);

        }

        // Marcar presupuesto como convertido y vincular factura
        $documento->setEstadoComercial('convertido');
        $documento->setFechaAceptacion(new \DateTime());
        $documento->setFacturaVinculada($factura);

        // Recalcular factura
        $this->documentoCalculatorService->recalcularDocumento($factura);

        $proyecto = $documento->getProyecto();
        if ($proyecto) {
            $this->proyectoService->recalcularProyecto($proyecto);
        }

        $this->em->persist($factura);
        $this->em->flush();

        return $factura;
    }
}