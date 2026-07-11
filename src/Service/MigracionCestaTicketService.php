<?php

namespace App\Service;

use App\Entity\Cestas;
use App\Entity\Detallecesta;
use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Repository\DocumentoRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Documento\SerieService;

final class MigracionCestaTicketService
{
    private const TIPO_IVA_TICKET = '21.00';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentoRepository $documentoRepository,
        private readonly DocumentoCalculatorService $documentoService,
        private readonly SerieService $numeracionService,
    ) {
    }

    /**
     * Convierte una Cestas antigua en un Documento tipo ticket.
     *
     * No ejecuta flush, para poder migrar varias cestas dentro
     * de una única transacción.
     */
    public function migrar(Cestas $cesta): Documento
    {
        $this->comprobarQueNoEsteMigrada($cesta);
        $this->validarCesta($cesta);

        $ticket = $this->crearDocumentoTicket($cesta);

        $posicion = 1;

        foreach ($cesta->getDetallecesta() as $detalle) {
            $linea = $this->crearLineaDesdeDetalle(
                $detalle,
                $posicion
            );

            $ticket->addLinea($linea);
            $posicion++;
        }

        /*
         * Debe ser el mismo método que utilizas actualmente
         * al recalcular presupuestos y facturas.
         *
         * Si en tu servicio se llama recalcularTotales(),
         * sustituye esta llamada por ese nombre.
         */
        $this->documentoService->recalcularDocumento($ticket);

        $this->validarTotales($cesta, $ticket);

        /*
         * Como la cesta antigua ya estaba cobrada,
         * de momento marcamos directamente el ticket como cobrado.
         *
         * Cuando me pases DocumentoCobro podremos sustituir esto
         * por la creación de un cobro real.
         */
        $ticket->setTotalCobrado($ticket->getTotal());
        $ticket->setEstadoCobro('cobrado');

        $this->entityManager->persist($ticket);

        return $ticket;
    }

    private function crearDocumentoTicket(Cestas $cesta): Documento
    {
        $ticket = new Documento();

        $ticket->setTipoDocumento('ticket');

        /*
         * Consume la numeración actual:
         * por ejemplo, si el último es T2026-0020,
         * este será T2026-0021.
         */
        $this->numeracionService->asignarNumeracion($ticket);

        $ticket->setFechaEmision(
            \DateTime::createFromInterface($cesta->getFechaCs())
        );

        $ticket->setCliente(null);
        $ticket->setProyecto(null);

        $ticket->setEstadoComercial('aceptado');
        $ticket->setEstadoCobro('pendiente');
        $ticket->setEstadoEjecucion('no_aplica');

        $ticket->setOrigenMigracion('cesta');
        $ticket->setOrigenMigracionId($cesta->getId());
        $ticket->setReferenciaAnterior($cesta->getNumticketCs());

        $ticket->setNotas(sprintf(
            'Migrado desde Cestas #%d. Número anterior: %s. Pago anterior: %s.',
            $cesta->getId(),
            $cesta->getNumticketCs() ?: 'sin número',
            $cesta->getTipopagoCs() ?: 'sin especificar'
        ));

        return $ticket;
    }

    private function crearLineaDesdeDetalle(
        Detallecesta $detalle,
        int $posicion
    ): DocumentoLinea {
        $cantidad = $this->decimal(
            $detalle->getCantidadDc(),
            3
        );

        $pvpConIva = $this->decimal(
            $detalle->getPvpDc(),
            2
        );

        $tipoIva = self::TIPO_IVA_TICKET;

        $precioSinIva = $this->calcularPrecioSinIva(
            $pvpConIva,
            $tipoIva
        );

        $descuento = $this->normalizarDescuento(
            $detalle->getDescuentoDc()
        );

        $costeUnitario = $detalle->getPrecioDc() !== null
            ? $this->decimal($detalle->getPrecioDc(), 2)
            : '0.00';

        $subtotal = $this->calcularSubtotal(
            $cantidad,
            $precioSinIva,
            $descuento
        );

        $totalIva = $this->calcularIva(
            $subtotal,
            $tipoIva
        );

        $totalCoste = bcmul(
            $cantidad,
            $costeUnitario,
            2
        );

        $linea = new DocumentoLinea();

        $linea->setPosicion($posicion);
        $linea->setTipoLinea('producto');
        $linea->setDestinoFacturacion(
            DocumentoLinea::DESTINO_TICKET_TIENDA
        );

        $linea->setProducto($detalle->getProductoDc());
        $linea->setDescripcion(
            $this->obtenerDescripcion($detalle)
        );

        $linea->setCantidad($cantidad);
        $linea->setUnidad('ud');

        $linea->setPrecioUnitario($precioSinIva);
        $linea->setCosteUnitario($costeUnitario);
        $linea->setDescuento($descuento);
        $linea->setTipoIva($tipoIva);

        $linea->setSubtotal($subtotal);
        $linea->setTotalIva($totalIva);
        $linea->setTotalCoste($totalCoste);

        /*
         * La migración no debe volver a descontar stock.
         * La venta ya ocurrió en el sistema antiguo.
         */
        $linea->setAfectaStock(false);
        $linea->setStockMovido(false);

        $linea->setOrigenLinea('migracion_cesta');

        return $linea;
    }

    private function obtenerDescripcion(
        Detallecesta $detalle
    ): string {
        $texto = trim((string) $detalle->getTextoDc());

        if ($texto !== '') {
            return $texto;
        }

        $producto = $detalle->getProductoDc();

        if ($producto !== null) {
            /*
             * Ajusta getDescripcionPd() al getter real de Productos.
             */
            if (method_exists($producto, 'getDescripcionPd')) {
                $descripcion = trim(
                    (string) $producto->getDescripcionPd()
                );

                if ($descripcion !== '') {
                    return $descripcion;
                }
            }

            if (method_exists($producto, 'getNombrePd')) {
                $nombre = trim(
                    (string) $producto->getNombrePd()
                );

                if ($nombre !== '') {
                    return $nombre;
                }
            }

            return (string) $producto;
        }

        return 'Producto migrado';
    }

    private function comprobarQueNoEsteMigrada(
        Cestas $cesta
    ): void {
        $existente = $this->documentoRepository->findOneBy([
            'origenMigracion' => 'cesta',
            'origenMigracionId' => $cesta->getId(),
        ]);

        if ($existente !== null) {
            throw new \DomainException(sprintf(
                'La cesta %d ya fue migrada como %s.',
                $cesta->getId(),
                $existente->getNumeroFormateado()
            ));
        }
    }

    private function validarCesta(Cestas $cesta): void
    {
        if ($cesta->getFechaCs() === null) {
            throw new \DomainException(sprintf(
                'La cesta %d no tiene fecha.',
                $cesta->getId()
            ));
        }

        if ($cesta->getDetallecesta()->isEmpty()) {
            throw new \DomainException(sprintf(
                'La cesta %d no tiene líneas.',
                $cesta->getId()
            ));
        }

        if ($cesta->getImporteTotCs() === null) {
            throw new \DomainException(sprintf(
                'La cesta %d no tiene importe total.',
                $cesta->getId()
            ));
        }

        foreach ($cesta->getDetallecesta() as $detalle) {
            if ($detalle->getCantidadDc() === null) {
                throw new \DomainException(sprintf(
                    'La cesta %d tiene una línea sin cantidad.',
                    $cesta->getId()
                ));
            }

            if ($detalle->getPvpDc() === null) {
                throw new \DomainException(sprintf(
                    'La cesta %d tiene una línea sin PVP.',
                    $cesta->getId()
                ));
            }
        }
    }

    private function validarTotales(
        Cestas $cesta,
        Documento $ticket
    ): void {
        $totalAntiguo = $this->decimal(
            $cesta->getImporteTotCs(),
            2
        );

        $totalNuevo = $this->decimal(
            $ticket->getTotal(),
            2
        );

        $diferencia = bcsub(
            $totalAntiguo,
            $totalNuevo,
            2
        );

        // Permitimos una diferencia máxima de 1 céntimo
        if (
            bccomp($diferencia, '0.01', 2) > 0
            || bccomp($diferencia, '-0.01', 2) < 0
        ) {
            throw new \DomainException(sprintf(
                'La cesta %d no cuadra. Total antiguo: %s €. Total nuevo: %s €. Diferencia: %s €.',
                $cesta->getId(),
                $totalAntiguo,
                $totalNuevo,
                $diferencia
            ));
        }
    }

    private function calcularPrecioSinIva(
        string $precioConIva,
        string $tipoIva
    ): string {
        $divisor = bcadd(
            '1',
            bcdiv($tipoIva, '100', 6),
            6
        );

        return bcdiv(
            $precioConIva,
            $divisor,
            2
        );
    }

    private function calcularSubtotal(
        string $cantidad,
        string $precioUnitario,
        string $descuento
    ): string {
        $bruto = bcmul(
            $cantidad,
            $precioUnitario,
            4
        );

        if (bccomp($descuento, '0.00', 2) === 0) {
            return $this->redondear($bruto, 2);
        }

        $factorDescuento = bcsub(
            '1',
            bcdiv($descuento, '100', 6),
            6
        );

        return $this->redondear(
            bcmul($bruto, $factorDescuento, 6),
            2
        );
    }

    private function calcularIva(
        string $subtotal,
        string $tipoIva
    ): string {
        $iva = bcmul(
            $subtotal,
            bcdiv($tipoIva, '100', 6),
            6
        );

        return $this->redondear($iva, 2);
    }

    private function normalizarDescuento(
        int|float|string|null $descuento
    ): string {
        if ($descuento === null) {
            return '0.00';
        }

        $resultado = $this->decimal(
            $descuento,
            2
        );

        if (bccomp($resultado, '0.00', 2) < 0) {
            throw new \DomainException(
                'El descuento no puede ser negativo.'
            );
        }

        if (bccomp($resultado, '100.00', 2) > 0) {
            throw new \DomainException(
                'El descuento no puede superar el 100 %.'
            );
        }

        return $resultado;
    }

    private function decimal(
        int|float|string|null $valor,
        int $escala
    ): string {
        if ($valor === null || $valor === '') {
            $valor = 0;
        }

        return number_format(
            (float) $valor,
            $escala,
            '.',
            ''
        );
    }

    private function redondear(
        string $valor,
        int $escala
    ): string {
        $incremento = '0.' . str_repeat('0', $escala) . '5';

        if (bccomp($valor, '0', 6) >= 0) {
            return bcadd($valor, $incremento, $escala);
        }

        return bcsub($valor, $incremento, $escala);
    }
}