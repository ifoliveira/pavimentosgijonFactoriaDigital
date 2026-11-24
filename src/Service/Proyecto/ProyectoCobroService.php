<?php

namespace App\Service\Proyecto;

use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Entity\Banco;
use App\Repository\DocumentoRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Efectivo\EfectivoService;

class ProyectoCobroService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProyectoCalculatorService $proyectoCalculatorService,
        private EfectivoService $efectivoService,
        private FacturaAnticipoService $facturaAnticipoService,
        private DocumentoRepository $documentos,
        private FacturaFinalService $facturaFinalService,
    ) {
    }

    public function registrarCobro(
        Proyecto $proyecto,
        \DateTimeInterface $fecha,
        string $metodo,
        float $importeBruto,
        float $porcentajeRecargo = 0,
        float $importeRecargo = 0,
        float $importeNeto = 0,
        ?string $referencia = null,
        ?string $notas = null,
        ?bool $confirmarFacturaFinal = null,
    ): ProyectoCobro {
        $args = func_get_args();
        return $this->em->wrapInTransaction(function () use ($proyecto, $args): ProyectoCobro {
            $this->em->refresh($proyecto, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            return $this->registrarCobroBloqueado(...$args);
        });
    }

    private function registrarCobroBloqueado(
        Proyecto $proyecto,
        \DateTimeInterface $fecha,
        string $metodo,
        float $importeBruto,
        float $porcentajeRecargo = 0,
        float $importeRecargo = 0,
        float $importeNeto = 0,
        ?string $referencia = null,
        ?string $notas = null,
        ?bool $confirmarFacturaFinal = null,
    ): ProyectoCobro {
        if (!is_finite($importeBruto) || bccomp($this->money($importeBruto), '0.00', 2) <= 0) {
            throw new \RuntimeException(
                'El importe del cobro debe ser mayor que cero.'
            );
        }

        $this->proyectoCalculatorService->recalcularProyecto($proyecto, false);
        if (bccomp($this->money($importeBruto), $proyecto->getPendienteCobro(), 2) > 0) {
            throw new \RuntimeException(sprintf(
                'El cobro supera el importe pendiente de la obra (%s €).',
                number_format((float) $proyecto->getPendienteCobro(), 2, ',', '.')
            ));
        }

        $facturaFinal = $this->documentos->findFacturaCompletaDeProyecto($proyecto);
        $restante = bcsub($proyecto->getPendienteCobro(), $this->money($importeBruto), 2);
        $cercanoAlTotal = bccomp($restante, '0.00', 2) > 0 && bccomp($restante, '5.00', 2) <= 0;
        if ($facturaFinal === null && $cercanoAlTotal && $confirmarFacturaFinal === null) {
            throw new ConfirmacionFacturaFinalRequerida(sprintf(
                'Quedan %s € pendientes. ¿Generar la factura final aplicando ese importe como descuento de cierre? Aceptar: factura final. Cancelar: registrar un anticipo y mantener el saldo pendiente.',
                number_format((float) $restante, 2, ',', '.')
            ));
        }
        if ($facturaFinal === null && $confirmarFacturaFinal === true && bccomp($restante, '5.00', 2) > 0) {
            throw new \RuntimeException('El descuento de cierre no puede superar 5 €. Revisa el importe del cobro.');
        }
        $emitirFinal = bccomp($restante, '0.00', 2) === 0 || ($cercanoAlTotal && $confirmarFacturaFinal === true);
        $metodo = strtolower(trim($metodo));

        if ($metodo === '') {
            throw new \RuntimeException(
                'Debes indicar un método de pago.'
            );
        }

        if ($porcentajeRecargo > 0 && $importeRecargo <= 0) {
            $importeRecargo = round(
                $importeBruto * ($porcentajeRecargo / 100),
                2
            );
        }

        if ($importeNeto <= 0) {
            $importeNeto = round(
                $importeBruto - $importeRecargo,
                2
            );
        }

        $cobro = new ProyectoCobro();

        $cobro->setProyecto($proyecto);
        $cobro->setFecha($fecha);
        $cobro->setMetodo($metodo);
        $cobro->setImporteBruto($this->money($importeBruto));
        $cobro->setPorcentajeRecargo($this->money($porcentajeRecargo));
        $cobro->setImporteRecargo($this->money($importeRecargo));
        $cobro->setImporteNeto($this->money($importeNeto));
        $cobro->setReferencia($referencia ?: null);
        $cobro->setNotas($notas ?: null);

        /*
        * ============================================================
        * EFECTIVO
        * ============================================================
        *
        * Si el cliente paga en efectivo, el dinero entra
        * inmediatamente en caja.
        */
        if ($metodo === ProyectoCobro::METODO_EFECTIVO) {

            $movimientoEfectivo = $this->efectivoService->registrarEntrada(
                importe: $importeBruto,
                concepto: 'Cobro proyecto: ' . $proyecto->getNombre(),
                fecha: $fecha
            );

            $cobro->setEfectivo($movimientoEfectivo);
        }

        /*
        * ============================================================
        * BANCO
        * ============================================================
        *
        * Transferencia, tarjeta, Bizum, financiación...
        *
        * NO creamos ningún movimiento bancario ficticio.
        * ProyectoCobro.banco queda null hasta la conciliación.
        */

        $proyecto->addCobro($cobro);

        $this->em->persist($cobro);
        $this->em->flush();

        if ($facturaFinal === null) {
            if ($emitirFinal) {
                $this->facturaFinalService->emitir($proyecto, $restante);
            } else {
                $this->facturaAnticipoService->emitir($cobro);
            }
        }

        $this->proyectoCalculatorService->recalcularProyecto(
            $proyecto,
            false
        );

        $this->em->flush();

        return $cobro;
    }


    public function conciliarCobro(
        ProyectoCobro $cobro,
        Banco $banco
    ): void {

        /*
        * El cobro ya está conciliado.
        */
        if ($cobro->getBanco() !== null) {
            throw new \LogicException(
                'Este cobro ya está conciliado.'
            );
        }

        /*
        * El movimiento bancario ya está utilizado.
        */
        if ((bool) $banco->isConciliado()) {
            throw new \LogicException(
                'Este movimiento bancario ya está conciliado.'
            );
        }

        /*
        * Para conciliar un ProyectoCobro esperamos
        * siempre un movimiento positivo de Banco.
        */
        if ((float) $banco->getImporteBn() <= 0) {
            throw new \LogicException(
                'El movimiento bancario seleccionado no es un ingreso.'
            );
        }

        /*
        * Un cobro en efectivo no debería llegar
        * al conciliador bancario.
        */
        if ($cobro->getMetodo() === ProyectoCobro::METODO_EFECTIVO) {
            throw new \LogicException(
                'Los cobros en efectivo no se concilian con movimientos bancarios.'
            );
        }

        /*
        * Asociamos el movimiento real al cobro.
        */
        $cobro->setBanco($banco);

        /*
        * El movimiento deja de estar pendiente
        * de conciliación.
        */
        $banco->setConciliado(true);

        $this->em->flush();
    }

    public function eliminarCobro(ProyectoCobro $cobro): void
    {
        if ($cobro->getFacturaAnticipo()) {
            throw new \LogicException('No se puede anular un cobro con factura de anticipo emitida; requiere una rectificación.');
        }
        $proyecto = $cobro->getProyecto();

        if ($proyecto) {
            $proyecto->removeCobro($cobro);
        }

        $this->em->remove($cobro);

        if ($proyecto) {
            $this->proyectoCalculatorService->recalcularProyecto($proyecto, false);
        }

        $this->em->flush();
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
