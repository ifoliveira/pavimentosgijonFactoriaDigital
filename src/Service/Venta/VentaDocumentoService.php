<?php

namespace App\Service\Venta;

use App\Entity\Cestas;
use App\Entity\Documento;
use App\Entity\DocumentoCobro;
use App\Entity\DocumentoLinea;
use App\Entity\Efectivo;
use App\Entity\Forecast;
use App\Entity\Productos;
use App\Entity\SerieDocumento;
use App\Entity\Tiposmovimiento;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Documento\SerieService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class VentaDocumentoService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private DocumentoCalculatorService $calculator,
    ) {}

    public function crearVentaTpv(VentaTpvInput $input): VentaTpvResultado
    {
        // Una colisión puede ser el mismo UUID o la creación simultánea de una serie anual.
        for ($intento = 0; $intento < 2; ++$intento) {
            /** @var EntityManagerInterface $em */
            $em = $this->doctrine->getManager();
            $existente = $this->buscarExistente($em, $input);
            if ($existente !== null) {
                return new VentaTpvResultado($existente, true);
            }

            try {
                return $em->wrapInTransaction(function () use ($em, $input): VentaTpvResultado {
                    $documento = $this->crearDocumento($em, $input);
                    return new VentaTpvResultado($documento, false);
                });
            } catch (UniqueConstraintViolationException $e) {
                // wrapInTransaction revierte también numeración, cobro y movimiento de caja.
                $this->doctrine->resetManager();
                $existente = $this->buscarExistente($this->doctrine->getManager(), $input);
                if ($existente !== null) {
                    return new VentaTpvResultado($existente, true);
                }
                if ($intento === 1) {
                    throw $e;
                }
            }
        }

        throw new \LogicException('No se ha podido registrar la venta.');
    }

    private function buscarExistente(EntityManagerInterface $em, VentaTpvInput $input): Documento|Cestas|null
    {
        $criteria = ['tpvUuid' => strtolower($input->uuid)];
        $existente = $em->getRepository(Documento::class)->findOneBy($criteria)
            ?? $em->getRepository(Cestas::class)->findOneBy($criteria);

        if ($existente instanceof Documento && $existente->getTipoDocumento() !== 'ticket') {
            throw new \InvalidArgumentException('El UUID ya corresponde a una operación distinta de una venta.');
        }

        if ($existente !== null && $existente->getTpvCaja() !== $input->caja) {
            throw new \InvalidArgumentException('El UUID de la venta ya pertenece a otra caja.');
        }

        return $existente;
    }

    private function crearDocumento(EntityManagerInterface $em, VentaTpvInput $input): Documento
    {
        if ($input->lineas === []) {
            throw new \InvalidArgumentException('La venta debe contener al menos una linea.');
        }

        $documento = new Documento();
        $documento->setTipoDocumento('ticket');
        $documento->setEstadoComercial('aceptado');
        $documento->setEstadoEjecucion('no_aplica');
        $documento->setFechaEmision(\DateTime::createFromInterface($input->fecha));
        $documento->setTpvFecha(\DateTimeImmutable::createFromInterface($input->fecha));
        $documento->setTpvUuid(strtolower($input->uuid));
        $documento->setTpvCaja($input->caja);
        $documento->setTpvNumeroTicketLocal($input->numeroTicketLocal);
        $documento->setTpvFormaPago($input->formaPago);

        foreach ($input->lineas as $posicion => $inputLinea) {
            $producto = $em->getRepository(Productos::class)->find($inputLinea->productoId);
            if (!$producto instanceof Productos) {
                throw new \InvalidArgumentException(sprintf('Producto no encontrado: %d.', $inputLinea->productoId));
            }

            $iva = $inputLinea->tipoIva ?? 21.0;
            if ($inputLinea->cantidad <= 0 || !is_finite($inputLinea->precioAplicado) || $inputLinea->precioAplicado < 0
                || !is_finite($iva) || $iva < 0 || $iva > 100
                || !is_finite($inputLinea->descuento) || $inputLinea->descuento < 0 || $inputLinea->descuento > 100) {
                throw new \InvalidArgumentException('Cantidad, precio, descuento o IVA de la linea no valido.');
            }

            $linea = new DocumentoLinea();
            $linea->setPosicion($posicion + 1);
            $linea->setTipoLinea('producto');
            $linea->setOrigenLinea('tpv');
            $linea->setDestinoFacturacion(DocumentoLinea::DESTINO_TICKET_TIENDA);
            $linea->setProducto($producto);
            $linea->setDescripcion($inputLinea->texto ?? $producto->getDescripcionPd());
            $linea->setCantidad((string) $inputLinea->cantidad);
            $linea->setPrecioUnitarioConIva($this->decimal($inputLinea->precioAplicado));
            $linea->setTpvPrecioOriginal($inputLinea->precioOriginal === null ? null : $this->decimal($inputLinea->precioOriginal));
            $costeInformado = $inputLinea->coste;
            $costeProducto = (float) ($producto->getPrecioPd() ?? 0);

            // En el TPV no siempre conocemos el coste real de compra. En ese
            // caso usamos una estimación conservadora: 60 % del precio de
            // venta sin IVA (equivale a descontar un 40 %).
            if ($costeInformado !== null && is_finite((float) $costeInformado) && (float) $costeInformado > 0) {
                $costeUnitario = (float) $costeInformado;
            } elseif ($costeProducto > 0) {
                $costeUnitario = $costeProducto;
            } else {
                $precioSinIva = $inputLinea->precioAplicado / (1 + ($iva / 100));
                $costeUnitario = $precioSinIva * 0.60;
            }

            $linea->setCosteUnitario($this->decimal($costeUnitario));
            $linea->setDescuento($this->decimal($inputLinea->descuento));
            $linea->setTipoIva($this->decimal($iva));
            $documento->addLinea($linea);
        }

        $this->calculator->recalcularDocumento($documento);
        if ($input->totalInformado !== null && abs((float) $documento->getTotal() - $input->totalInformado) > 0.010001) {
            throw new \InvalidArgumentException(sprintf(
                'El total informado %.2f no coincide con el total calculado %s.',
                $input->totalInformado,
                $documento->getTotal()
            ));
        }

        // Se reutiliza el contador de Documentos dentro de la misma transacción de venta.
        $serieService = new SerieService($em, $em->getRepository(SerieDocumento::class));
        $serieService->asignarNumeracion($documento);

        $cobro = new DocumentoCobro();
        $cobro->setFecha(\DateTime::createFromInterface($input->fecha));
        $cobro->setMetodo(strtolower($input->formaPago));
        $cobro->setImporteBruto($documento->getTotal());
        $cobro->setImporteNeto($documento->getTotal());
        $cobro->setReferencia(strtolower($input->uuid));
        $cobro->setNotas('Venta TPV: ' . $input->caja);
        $documento->addCobro($cobro);
        $documento->setTotalCobrado($documento->getTotal());
        $documento->setEstadoCobro('cobrado');

        $tipoVentas = $em->getRepository(Tiposmovimiento::class)->findOneBy(['descripcionTm' => 'Ventas']);

        if (strtolower($input->formaPago) === 'efectivo') {
            $efectivo = new Efectivo();
            $efectivo->setTipoEf($tipoVentas);
            $efectivo->setConceptoEf('Venta TPV ' . $documento->getNumeroFormateado());
            $efectivo->setFechaEf(\DateTime::createFromInterface($input->fecha));
            $efectivo->setImporteEf((float) $documento->getTotal());
            $em->persist($efectivo);
        } elseif (strtolower($input->formaPago) === 'tarjeta') {
            if (!$tipoVentas instanceof Tiposmovimiento) {
                throw new \LogicException('No existe la categoría de movimientos "Ventas" para crear el Forecast de la venta TPV.');
            }

            // El abono del datáfono llega posteriormente al extracto bancario.
            // Lo dejamos pendiente para poder conciliarlo cuando se importe.
            $forecast = new Forecast();
            $forecast->setTipoFr($tipoVentas);
            $forecast->setConceptoFr('Venta TPV tarjeta ' . $documento->getNumeroFormateado() . ' (' . $input->caja . ')');
            $forecast->setFechaFr(\DateTime::createFromInterface($input->fecha));
            $forecast->setImporteFr((float) $documento->getTotal());
            $forecast->setOrigenFr('TPV');
            $forecast->setFijovarFr('V');
            $forecast->setEstadoFr('P');
            $em->persist($forecast);
        }

        $em->persist($documento);
        return $documento;
    }

    private function decimal(float $valor): string
    {
        if (!is_finite($valor) || abs($valor) > 99999999.99) {
            throw new \InvalidArgumentException('Importe fuera de rango.');
        }
        return number_format($valor, 2, '.', '');
    }
}
