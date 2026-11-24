<?php

namespace App\Service\Proyecto;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\Proyecto;
use App\Repository\DocumentoRepository;
use App\Service\Documento\SerieService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class FacturaFinalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DocumentoRepository $documentos,
        private SerieService $series,
        private ProyectoCalculatorService $calculator,
    ) {
    }

    public function emitir(Proyecto $proyecto, string $descuentoCierre = '0.00'): Documento
    {
        return $this->em->wrapInTransaction(function () use ($proyecto, $descuentoCierre): Documento {
            $this->em->refresh($proyecto, LockMode::PESSIMISTIC_WRITE);
            if ($existente = $this->documentos->findFacturaCompletaDeProyecto($proyecto)) {
                return $existente;
            }

            $presupuestos = $this->documentos->findPresupuestosAceptadosDeProyecto($proyecto);
            if (!$presupuestos) {
                throw new \RuntimeException('Debes aceptar un presupuesto antes de emitir la factura final.');
            }

            $factura = (new Documento())
                ->setTipoDocumento('factura')
                ->setCliente($proyecto->getCliente())
                ->setEstadoComercial('aceptado')
                ->setEstadoEjecucion($proyecto->isObraFinalizada() ? 'completada' : 'pendiente')
                ->setNotas('Factura final de la obra, descontados los anticipos emitidos.');

            foreach ($presupuestos as $presupuesto) {
                foreach ($presupuesto->getLineas() as $origen) {
                    $linea = $this->copiarLinea($origen);
                    $linea->setOrigenPresupuesto($presupuesto);
                    $linea->setPosicion($factura->getLineas()->count() + 1);
                    $factura->addLinea($linea);
                }
            }
            if ($factura->getLineas()->isEmpty()) {
                throw new \RuntimeException('Los presupuestos aceptados no contienen líneas.');
            }

            $anticipos = new \SplObjectStorage();
            foreach ($proyecto->getCobros() as $cobro) {
                $anticipo = $cobro->getFacturaAnticipo();
                if (!$anticipo || $anticipos->contains($anticipo)) {
                    continue;
                }
                $anticipos->attach($anticipo);
                foreach ($anticipo->getLineas() as $origen) {
                    $linea = $this->copiarLinea($origen);
                    $linea->setCantidad(bcmul($origen->getCantidad(), '-1', 3));
                    $linea->setSubtotal(bcmul($origen->getSubtotal(), '-1', 2));
                    $linea->setTotalIva(bcmul($origen->getTotalIva(), '-1', 2));
                    $linea->setCosteUnitario('0.00')->setTotalCoste('0.00');
                    $linea->setOrigenLinea('descuento_anticipo');
                    $linea->setDescripcion(sprintf(
                        'Descuento anticipo %s — importe factura: %s €',
                        $anticipo->getNumeroFormateado(),
                        number_format((float) $anticipo->getTotal(), 2, ',', '.')
                    ));
                    $linea->setPosicion($factura->getLineas()->count() + 1);
                    $factura->addLinea($linea);
                }
            }

            $this->aplicarDescuentoCierre($factura, $descuentoCierre);

            // Conservamos exactamente los céntimos de los documentos emitidos:
            // recalcular precio × cantidad podría introducir otro redondeo.
            $base = $iva = $coste = '0.00';
            foreach ($factura->getLineas() as $linea) {
                $base = bcadd($base, $linea->getSubtotal(), 2);
                $iva = bcadd($iva, $linea->getTotalIva(), 2);
                $coste = bcadd($coste, $linea->getTotalCoste(), 2);
            }
            $factura->setBaseImponible($base)->setTotalIva($iva)
                ->setTotal(bcadd($base, $iva, 2))->setTotalCoste($coste);
            if (bccomp($factura->getTotal(), '0.00', 2) < 0) {
                throw new \RuntimeException('Los anticipos superan el importe contratado. Revisa las rectificaciones antes de emitir la factura final.');
            }
            $factura->setEstadoCobro(bccomp($factura->getTotal(), '0', 2) === 0 ? 'cobrado' : 'pendiente');
            $this->series->asignarNumeracion($factura);
            $proyecto->addDocumento($factura);
            $this->em->persist($factura);
            $this->em->flush();
            $this->calculator->recalcularProyecto($proyecto, false);

            return $factura;
        });
    }

    private function aplicarDescuentoCierre(Documento $factura, string $descuento): void
    {
        if (bccomp($descuento, '0.00', 2) < 0 || bccomp($descuento, '5.00', 2) > 0) {
            throw new \RuntimeException('El descuento de cierre debe estar entre 0 y 5 €.');
        }
        if (bccomp($descuento, '0.00', 2) === 0) {
            return;
        }

        // Repartir el descuento entre los tipos de IVA del saldo de la factura.
        $grupos = [];
        foreach ($factura->getLineas() as $linea) {
            $tipo = $linea->getTipoIva();
            $grupos[$tipo] = bcadd($grupos[$tipo] ?? '0.00', bcadd($linea->getSubtotal(), $linea->getTotalIva(), 2), 2);
        }
        $grupos = array_filter($grupos, static fn ($total) => bccomp($total, '0.00', 2) > 0);
        $total = array_reduce($grupos, static fn ($suma, $importe) => bcadd($suma, $importe, 2), '0.00');
        if (bccomp($total, $descuento, 2) < 0) {
            throw new \RuntimeException('El descuento supera el saldo de la factura.');
        }
        $pendiente = $descuento;
        $ultimo = array_key_last($grupos);
        foreach ($grupos as $tipo => $importe) {
            $parte = $tipo === $ultimo ? $pendiente : bcdiv(bcmul($descuento, $importe, 6), $total, 2);
            $pendiente = bcsub($pendiente, $parte, 2);
            if (bccomp($parte, '0.00', 2) === 0) {
                continue;
            }
            $base = number_format(round((float) $parte / (1 + (float) $tipo / 100), 2), 2, '.', '');
            $iva = bcsub($parte, $base, 2);
            $factura->addLinea((new DocumentoLinea())
                ->setTipoLinea('servicio')->setDescripcion('Descuento de cierre confirmado al registrar el cobro')
                ->setCantidad('-1.000')->setPrecioUnitario($base)->setPrecioUnitarioConIva($parte)
                ->setTipoIva((string) $tipo)->setSubtotal(bcmul($base, '-1', 2))
                ->setTotalIva(bcmul($iva, '-1', 2))->setCosteUnitario('0.00')->setTotalCoste('0.00')
                ->setOrigenLinea('factura_final')->setAfectaStock(false)->setStockMovido(false)
                ->setPosicion($factura->getLineas()->count() + 1));
        }
    }

    private function copiarLinea(DocumentoLinea $origen): DocumentoLinea
    {
        return (new DocumentoLinea())
            ->setTipoLinea($origen->getTipoLinea())
            ->setProducto($origen->getProducto())
            ->setCatalogoProducto($origen->getCatalogoProducto())
            ->setDescripcion($origen->getDescripcion() ?? '')
            ->setCantidad($origen->getCantidad())
            ->setUnidad($origen->getUnidad())
            ->setPrecioUnitario($origen->getPrecioUnitario())
            ->setPrecioUnitarioConIva($origen->getPrecioUnitarioConIva())
            ->setDescuento($origen->getDescuento())
            ->setTipoIva($origen->getTipoIva())
            ->setSubtotal($origen->getSubtotal())
            ->setTotalIva($origen->getTotalIva())
            ->setCosteUnitario($origen->getCosteUnitario())
            ->setTotalCoste($origen->getTotalCoste())
            ->setDestinoFacturacion($origen->getDestinoFacturacion())
            ->setOrigenLinea('factura_final')
            ->setAfectaStock(false)->setStockMovido(false);
    }
}
