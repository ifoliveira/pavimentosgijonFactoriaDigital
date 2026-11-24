<?php

namespace App\Service\Proyecto;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Entity\ProyectoCobro;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Documento\SerieService;
use Doctrine\ORM\EntityManagerInterface;

final class FacturaAnticipoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerieService $series,
        private DocumentoCalculatorService $calculator,
    ) {
    }

    public function emitir(ProyectoCobro $cobro): Documento
    {
        if ($cobro->getFacturaAnticipo()) {
            return $cobro->getFacturaAnticipo();
        }

        $proyecto = $cobro->getProyecto();
        if (!$proyecto) {
            throw new \RuntimeException('Cobro sin proyecto.');
        }

        if ((float) $cobro->getImporteBruto() > (float) $proyecto->getTotalPresupuestado()) {
            throw new \RuntimeException('El anticipo supera el importe contratado vigente.');
        }

        $ivas = [];
        foreach ($proyecto->getDocumentos() as $documento) {
            if (
                $documento->getTipoDocumento() !== 'presupuesto'
                || !in_array($documento->getEstadoComercial(), ['aceptado', 'convertido'], true)
            ) {
                continue;
            }

            foreach ($documento->getLineas() as $linea) {
                if ((float) $linea->getSubtotal() !== 0.0) {
                    $ivas[(string) $linea->getTipoIva()] = $linea->getTipoIva();
                }
            }
        }

        if (count($ivas) !== 1) {
            throw new \RuntimeException(
                'El proyecto debe tener un único IVA (10 % o 21 %) en todos sus presupuestos aceptados antes de emitir un anticipo.'
            );
        }

        $iva = (string) reset($ivas);
        $factura = (new Documento())
            ->setTipoDocumento('factura')
            ->setCliente($proyecto->getCliente())
            ->setProyecto($proyecto)
            ->setFechaEmision($cobro->getFecha())
            ->setEstadoComercial('aceptado')
            ->setEstadoCobro('cobrado')
            ->setEstadoEjecucion($proyecto->isObraFinalizada() ? 'completada' : 'pendiente')
            ->setNotas('Factura de anticipo del cobro #' . $cobro->getId());
        $this->series->asignarNumeracion($factura);

        $linea = (new DocumentoLinea())
            ->setPosicion(1)
            ->setTipoLinea('servicio')
            ->setDestinoFacturacion(DocumentoLinea::DESTINO_FACTURA_OBRA)
            ->setDescripcion('Anticipo proyecto')
            ->setCantidad('1.000')
            ->setUnidad('ud')
            ->setPrecioUnitarioConIva($cobro->getImporteBruto())
            ->setCosteUnitario('0.00')
            ->setDescuento('0.00')
            ->setTipoIva($iva)
            ->setAfectaStock(false)
            ->setStockMovido(false)
            ->setOrigenLinea('anticipo');
        $factura->addLinea($linea);

        $this->calculator->recalcularDocumento($factura);
        $factura->setTotalCobrado($factura->getTotal());
        $proyecto->addDocumento($factura);

        $this->em->persist($factura);
        $cobro->setFacturaAnticipo($factura);

        // El recalculador consulta las facturas mediante repositorio. Persistimos
        // el anticipo antes de invocarlo para que no calcule contra el estado previo.
        $this->em->flush();

        return $factura;
    }
}
