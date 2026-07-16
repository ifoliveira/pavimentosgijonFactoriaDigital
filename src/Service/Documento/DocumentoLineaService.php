<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Entity\StockReserva;
use App\Entity\DocumentoLinea;
use App\Repository\ProductosRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DocumentoLineaRepository;
use App\Service\Proyecto\ProyectoCalculatorService;
use App\Entity\CatalogoProducto;
use App\Repository\StockMovimientoRepository;
use App\Repository\StockReservaRepository;


class DocumentoLineaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductosRepository $productosRepository,
        private DocumentoLineaRepository $lineaRepository,
        private ProyectoCalculatorService $proyectoService,
        private StockMovimientoRepository $stockMovimientoRepository,
        private StockReservaRepository $stockReservaRepository
    ) {}

    public function crearLinea(
        Documento $documento,
        string $descripcion,
        float $cantidad,
        float $precio,
        float $descuento,
        ?int $productoId,
        int $lineaId,
        string $tipo,
        string $destinoFacturacion = DocumentoLinea::DESTINO_PENDIENTE,
        string $origenLinea = 'manual'
    ): DocumentoLinea {


        if ($lineaId) {
            // editar línea existente
            $linea = $this->lineaRepository->find($lineaId);

            if (!$linea) {
                throw new \RuntimeException('No se ha encontrado la línea a editar.');
            }

            if ($linea->getDocumento()?->getId() !== $documento->getId()) {
                throw new \RuntimeException('La línea no pertenece a este documento.');
            }

            $linea->setDocumento($documento);

            if (!$documento->getLineas()->contains($linea)) {
                $documento->addLinea($linea);
            }

            $linea->setDescripcion(trim($descripcion));
            $linea->setTipoLinea($tipo);

        } else {
            $linea = new DocumentoLinea();
            $linea->setDocumento($documento);
            $linea->setDescripcion(trim($descripcion));
            $linea->setTipoLinea($tipo);
            $linea->setUnidad('ud');
            $linea->setPosicion(count($documento->getLineas()) + 1);

            $documento->addLinea($linea);
        }        

        if (!$destinoFacturacion) {
            $destinoFacturacion = $this->resolverDestinoFacturacionPorDefecto($tipo);
        }

        $linea->setDestinoFacturacion($destinoFacturacion);

        if ($tipo === 'comentario') {
            $linea->setCantidad('1.000');
            $linea->setPrecioUnitario('0.00');
            $linea->setCosteUnitario('0.00');
            $linea->setDescuento('0.00');
            $linea->setTipoIva('21.00');
            $linea->setSubtotal('0.00');
            $linea->setTotalIva('0.00');
            $linea->setTotalCoste('0.00');
            $linea->setProducto(null);
            $linea->setCatalogoProducto(null);
            $linea->setOrigenLinea('manual');
            $linea->setAfectaStock(false);
            $linea->setStockMovido(false);

            $this->em->persist($linea);
            $this->recalcularTotalesDocumento($documento);

            if ($documento->getProyecto()) {
                $this->proyectoService->recalcularProyecto($documento->getProyecto());
            }

            $this->em->flush();

            return $linea;
        }

        $linea->setCantidad(number_format($cantidad, 3, '.', ''));
        $linea->setDescuento(number_format($descuento, 2, '.', ''));
        $linea->setTipoIva('21.00');

        /*
        * REGLA NUEVA:
        * - Si hay productoId, viene de la tabla Productos.
        * - Si no hay productoId, la línea puede seguir siendo tipo producto,
        *   pero sin producto asociado.
        */
        if ($productoId) {
            $producto = $this->productosRepository->find($productoId);

            if ($producto) {
                $linea->setProducto($producto);
                $linea->setOrigenLinea('producto');
                $linea->setCatalogoProducto(null);

                if (method_exists($producto, 'getPrecioPd')) {
                    $linea->setPrecioCosteUnitario(number_format((float) $producto->getPrecioPd(), 2, '.', ''));
                } else {
                    $linea->setPrecioCosteUnitario('0.00');
                }
            } else {
                // Si por lo que sea llega un id inválido, no bloqueamos la creación.
                $linea->setProducto(null);
                $linea->setCatalogoProducto(null);
                $linea->setOrigenLinea($origenLinea ?: 'manual');
                $linea->setCosteUnitario('0.00');
            }

          

        } else {
            $linea->setProducto(null);
            $linea->setCatalogoProducto(null);

            // Aquí está el cambio importante:
            // respetamos el origen que venga del formulario.
            $linea->setOrigenLinea($origenLinea ?: 'manual');

            // De momento, si no hay producto/stock asociado, coste 0.
            // Más adelante, si viene de stock, aquí se podrá coger el coste de StockReserva.
            $linea->setCosteUnitario('0.00');
        }

        /*
        * De momento, una línea manual no mueve stock.
        * Cuando añadas StockReserva, entonces:
        * origenLinea = stock
        * afectaStock = true
        */
        if ($linea->getOrigenLinea() === 'stock') {
            $linea->setAfectaStock(true);
        } else {
            $linea->setAfectaStock(false);
        }

        $this->em->persist($linea);

        $this->recalcularImportesLinea($linea, $precio);
        $this->recalcularTotalesDocumento($documento);

        $this->crearReservaSiHayStockDisponible($linea, $documento, (float) $linea->getCantidad());

      

        if ($documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }

        $this->em->flush();

        return $linea;
    }

    private function crearReservaSiHayStockDisponible(
        DocumentoLinea $linea,
        Documento $documento,
        float $cantidad
    ): void {
        $producto = $linea->getProducto();

        if (!$producto) {
            return;
        }

        // Si la línea ya tenía reserva, no crear otra.
        if ($linea->getStockReserva()) {
            return;
        }

        $stockFisico = $this->stockMovimientoRepository->getStockFisicoPorProducto($producto);
        $stockReservado = $this->stockReservaRepository->getCantidadReservadaPorProducto($producto);
        $stockDisponible = $stockFisico - $stockReservado;

        if ($stockDisponible < $cantidad) {
            $linea->setAfectaStock(false);
            return;
        }

        $reserva = new StockReserva();

        $reserva->setProducto($producto);
        $reserva->setDocumento($documento);
        $reserva->setDocumentoLinea($linea);
        $reserva->setProyecto($documento->getProyecto());
        $reserva->setDescripcionProducto($linea->getDescripcion());
        $reserva->setCantidad($cantidad);
        $reserva->setCosteUnitario($linea->getCosteUnitario());
        $reserva->setEstado(StockReserva::ESTADO_RESERVADA);
        $reserva->setFechaCaducidad((new \DateTime())->modify('+30 days'));

        $reserva->setObservaciones(
            'Reserva creada automáticamente desde documento ' .
            ($documento->getId() ?: '')
        );

        $linea->setStockReserva($reserva);
        $linea->setOrigenLinea('stock');
        $linea->setAfectaStock(true);
        $linea->setStockMovido(false);

        $this->em->persist($reserva);
    }

    private function recalcularImportesLinea(DocumentoLinea $linea, float $precioConIva): void
    {
        $cantidad = (float) $linea->getCantidad();
        $descuento = (float) $linea->getDescuento();
        $tipoIva = (float) $linea->getTipoIva();
        $costeUnitario = (float) $linea->getCosteUnitario();

        if ($tipoIva < 0) {
            $tipoIva = 0;
        }

        if ($cantidad <= 0) {
            $cantidad = 1;
        }

        $precioConIva = round($precioConIva, 2);

        /*
        * 1. Precio unitario sin IVA
        * Este es el que se guarda en precioUnitario
        */
        $precioSinIva = $precioConIva / (1 + ($tipoIva / 100));

        /*
        * 2. Total final con IVA, partiendo SIEMPRE del precio con IVA
        */
        $totalBrutoConIva = $cantidad * $precioConIva;
        $importeDescuentoConIva = $totalBrutoConIva * ($descuento / 100);
        $totalConIva = round($totalBrutoConIva - $importeDescuentoConIva, 2);

        /*
        * 3. Base imponible total sin IVA
        */
        $subtotalBruto = $cantidad * $precioSinIva;
        $importeDescuentoSinIva = $subtotalBruto * ($descuento / 100);
        $subtotal = round($subtotalBruto - $importeDescuentoSinIva, 2);

        /*
        * 4. IVA calculado por diferencia
        * Así el total SIEMPRE cuadra con el precio con IVA
        */
        $totalIva = round($totalConIva - $subtotal, 2);

        /*
        * 5. Coste
        */
        $totalCoste = round($cantidad * $costeUnitario, 2);

        $linea->setPrecioUnitario(number_format($precioSinIva, 4, '.', ''));
        $linea->setSubtotal(number_format($subtotal, 2, '.', ''));
        $linea->setTotalIva(number_format($totalIva, 2, '.', ''));
        $linea->setTotalCoste(number_format($totalCoste, 2, '.', ''));
    }

    public function recalcularTotalesDocumento(Documento $documento): void
        {
            $baseImponible = 0.0;
            $totalIva = 0.0;
            $totalCoste = 0.0;

            foreach ($documento->getLineas() as $linea) {
                $baseImponible += (float) $linea->getSubtotal();
                $totalIva += (float) $linea->getTotalIva();
                if ( (float) $linea->getPrecioCosteUnitario() <> 0) {
                    $totalCoste += (float) $linea->getPrecioCosteUnitario() * (float) $linea->getCantidad();
                } else {
                    $totalCoste += (float) $linea->getSubtotal();
                }
            }

            $documento->setBaseImponible(number_format($baseImponible, 2, '.', ''));
            $documento->setTotalIva(number_format($totalIva, 2, '.', ''));
            $documento->setTotal(number_format($baseImponible + $totalIva, 2, '.', ''));
            $documento->setTotalCoste(number_format($totalCoste, 2, '.', ''));
        }  

    public function eliminarLinea(DocumentoLinea $linea): void
    {
        $documento = $linea->getDocumento();

        if (!$documento) {
            throw new \RuntimeException('La línea no tiene documento asociado.');
        }

        $documento->removeLinea($linea);
        $this->em->remove($linea);

        $this->reordenarPosiciones($documento);
        $this->recalcularTotalesDocumento($documento);

        if ($documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }        

        $this->em->flush();
    }

    private function reordenarPosiciones(Documento $documento): void
    {
        $posicion = 1;

        $lineas = $documento->getLineas()->toArray();

        usort($lineas, static function (DocumentoLinea $a, DocumentoLinea $b) {
            return $a->getPosicion() <=> $b->getPosicion();
        });

        foreach ($lineas as $linea) {
            $linea->setPosicion($posicion);
            $posicion++;
        }
    }    

    public function crearLineaDesdeConfigurador(
        Documento $documento,
        string $descripcion,
        float $cantidad,
        float $precioConIva,
        float $costeUnitario,
        string $tipoLinea,
        ?CatalogoProducto $catalogoProducto = null,
        string $origenLinea = 'configurador',
        float $tipoIva = 21.00,
        bool $flush = true,
        string $destinoFacturacion = null,
        ?float $ivaCoste = null,
        bool $tieneRecargoEquivalencia = false,
        ?float $porcentajeRecargoEquivalencia = null
    ): DocumentoLinea { 
        $linea = new DocumentoLinea();

        $linea->setDocumento($documento);
        $linea->setDescripcion(trim($descripcion));
        $linea->setTipoLinea($tipoLinea);
        $linea->setUnidad('ud');
        $linea->setPosicion(count($documento->getLineas()) + 1);

        $linea->setCantidad(number_format($cantidad, 3, '.', ''));
        $linea->setDescuento('0.00');
        $linea->setTipoIva(number_format($tipoIva, 2, '.', ''));
        $linea->setCosteUnitario(number_format($costeUnitario, 2, '.', ''));
        $ivaCoste = $ivaCoste ?? 0.00;


        $porcentajeRecargoEquivalencia = $porcentajeRecargoEquivalencia ?? 0.00;

        $importeIvaCosteUnitario = $costeUnitario * ($ivaCoste / 100);
        $importeRecargoUnitario = $costeUnitario * ($porcentajeRecargoEquivalencia / 100);

        $precioCosteUnitario = $costeUnitario
            + $importeIvaCosteUnitario
            + $importeRecargoUnitario;

        $linea->setCosteUnitarioBase(number_format($costeUnitario, 2, '.', ''));
        $linea->setPorcentajeIva(number_format($ivaCoste, 2, '.', ''));
        $linea->setImporteIvaUnitario(number_format($importeIvaCosteUnitario, 2, '.', ''));
        $linea->setTieneRecargoEquivalencia($tieneRecargoEquivalencia);
        $linea->setPorcentajeRecargoEquivalencia(number_format($porcentajeRecargoEquivalencia, 2, '.', ''));
        $linea->setImporteRecargoUnitario(number_format($importeRecargoUnitario, 2, '.', ''));
        $linea->setPrecioCosteUnitario(number_format($precioCosteUnitario, 2, '.', ''));        

        $linea->setProducto(null);
        $linea->setCatalogoProducto($catalogoProducto);
        $linea->setOrigenLinea($origenLinea);

        if (!$destinoFacturacion) {
            $destinoFacturacion = $this->resolverDestinoFacturacionPorDefecto($tipoLinea);
        }

        $linea->setDestinoFacturacion($destinoFacturacion);        

        $documento->addLinea($linea);

 

        $this->em->persist($linea);

        $this->recalcularImportesLinea($linea, $precioConIva);

        if ($flush) {
            $this->recalcularTotalesDocumento($documento);

            if ($documento->getProyecto()) {
                $this->proyectoService->recalcularProyecto($documento->getProyecto());
            }

            $this->em->flush();
        }

        return $linea;
    }    

    public function eliminarLineasDocumento(
        Documento $documento,
        bool $flush = true
    ): void {
        foreach ($documento->getLineas()->toArray() as $linea) {
            $documento->removeLinea($linea);
            $this->em->remove($linea);
        }

        $this->recalcularTotalesDocumento($documento);

        if ($documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }

        if ($flush) {
            $this->em->flush();
        }
    }   
    
    public function eliminarLineasDocumentoPorOrigenes(
        Documento $documento,
        array $origenes,
        bool $flush = true
    ): void {
        foreach ($documento->getLineas()->toArray() as $linea) {
            if (!in_array($linea->getOrigenLinea(), $origenes, true)) {
                continue;
            }

            $documento->removeLinea($linea);
            $this->em->remove($linea);
        }

        $this->recalcularTotalesDocumento($documento);

        if ($documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }

        if ($flush) {
            $this->em->flush();
        }
    }

    public function recalcularDocumentoCompleto(
        Documento $documento,
        bool $flush = true
    ): void {
        $this->reordenarPosiciones($documento);
        $this->recalcularTotalesDocumento($documento);

        if ($documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }

        if ($flush) {
            $this->em->flush();
        }
    }    

    private function resolverDestinoFacturacionPorDefecto(string $tipo): string
    {
        return match ($tipo) {
            'producto' => DocumentoLinea::DESTINO_TICKET_TIENDA,
            'servicio', 'mano_obra', 'descuento' => DocumentoLinea::DESTINO_FACTURA_OBRA,
            'comentario' => DocumentoLinea::DESTINO_NO_FACTURABLE,
            default => DocumentoLinea::DESTINO_PENDIENTE,
        };
    }
}