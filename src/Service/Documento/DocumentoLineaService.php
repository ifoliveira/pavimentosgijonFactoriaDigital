<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Repository\ProductosRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DocumentoLineaRepository;
use App\Service\Proyecto\ProyectoCalculatorService;


class DocumentoLineaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductosRepository $productosRepository,
        private DocumentoLineaRepository $lineaRepository,
        private ProyectoCalculatorService $proyectoService,
    ) {}

    public function crearLinea(
        Documento $documento,
        string $descripcion,
        float $cantidad,
        float $precio,
        float $descuento,
        ?int $productoId,
        float $lineaId,
        string $tipo
    ): DocumentoLinea {


        if ($lineaId) {
            // editar línea existente
            $linea = $this->lineaRepository->find($lineaId);

        } else {

            $linea = new DocumentoLinea();
            $linea->setDocumento($documento);
            $linea->setDescripcion(trim($descripcion));
            $linea->setTipoLinea($tipo);
            $linea->setUnidad('ud');
            $linea->setPosicion(count($documento->getLineas()) + 1);
        }

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

            $this->entityManager->persist($linea);

            return $linea;
        }

        $linea->setCantidad(number_format($cantidad, 3, '.', ''));
        $linea->setDescuento(number_format($descuento, 2, '.', ''));
        $linea->setTipoIva('21.00');

        if ($productoId) {
            $producto = $this->productosRepository->find($productoId);
            $linea->setProducto($producto);

            if ($producto && method_exists($producto, 'getCoste')) {
                $linea->setCosteUnitario(number_format((float) $producto->getCoste(), 2, '.', ''));
            } else {
                $linea->setCosteUnitario('0.00');
            }
        } else {
            $linea->setProducto(null);
            $linea->setCosteUnitario('0.00');
        }

        $documento->addLinea($linea);
        $this->em->persist($linea);
        $this->recalcularImportesLinea($linea, $precio);
        $this->recalcularTotalesDocumento($documento);
        if ($documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }

        $this->em->flush();

        return $linea;
    }

    private function recalcularImportesLinea(DocumentoLinea $linea, float $precioSinIva): void
    {
        $cantidad = (float) $linea->getCantidad();
        $descuento = (float) $linea->getDescuento();
        $tipoIva = (float) $linea->getTipoIva();
        $costeUnitario = (float) $linea->getCosteUnitario();

        if ($tipoIva < 0) {
            $tipoIva = 0;
        }



        // Precio unitario sin IVA
        $precioConIva =  $tipoIva > 0
            ? (float)  $precioSinIva * (1 + ($tipoIva / 100))
            : (float)  $precioSinIva;

        $precioConIva = round($precioConIva, 2);

        // Total bruto con IVA
        $totalBrutoConIva = $cantidad * $precioConIva;

        // Descuento sobre el total con IVA
        $importeDescuentoConIva = $totalBrutoConIva * ($descuento / 100);

        // Total final con IVA
        $totalConIva = round($totalBrutoConIva - $importeDescuentoConIva, 2);

        // Subtotal sin IVA
        $subtotalBruto = $cantidad * $precioSinIva;
        $importeDescuentoSinIva = $subtotalBruto * ($descuento / 100);
        $subtotal = round($subtotalBruto - $importeDescuentoSinIva, 2);

        // IVA ajustado para cuadrar con el total
        $totalIva = round($totalConIva - $subtotal, 2);

        // Coste total
        $totalCoste = round($cantidad * $costeUnitario, 2);

        $linea->setPrecioUnitario(number_format($precioSinIva, 2, '.', ''));
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
                $totalCoste += (float) $linea->getTotalCoste();
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
}