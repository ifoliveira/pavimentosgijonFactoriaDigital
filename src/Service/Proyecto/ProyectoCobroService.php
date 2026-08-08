<?php

namespace App\Service\Proyecto;

use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use App\Entity\Banco;

use Doctrine\ORM\EntityManagerInterface;
use App\Service\Efectivo\EfectivoService;

class ProyectoCobroService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProyectoCalculatorService $proyectoCalculatorService,
        private EfectivoService $efectivoService,
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
    ): ProyectoCobro {

        if ($importeBruto <= 0) {
            throw new \RuntimeException(
                'El importe del cobro debe ser mayor que cero.'
            );
        }

        $metodo = trim($metodo);

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