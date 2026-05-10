<?php

namespace App\Service\Proyecto;

use App\Entity\Proyecto;
use App\Entity\ProyectoCobro;
use Doctrine\ORM\EntityManagerInterface;

class ProyectoCobroService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProyectoCalculatorService $proyectoCalculatorService,
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
            throw new \RuntimeException('El importe del cobro debe ser mayor que cero.');
        }

        $metodo = trim($metodo);

        if ($metodo === '') {
            throw new \RuntimeException('Debes indicar un método de pago.');
        }

        if ($porcentajeRecargo > 0 && $importeRecargo <= 0) {
            $importeRecargo = round($importeBruto * ($porcentajeRecargo / 100), 2);
        }

        if ($importeNeto <= 0) {
            $importeNeto = round($importeBruto - $importeRecargo, 2);
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

        $proyecto->addCobro($cobro);

        $this->em->persist($cobro);

        $this->proyectoCalculatorService->recalcularProyecto($proyecto, false);

        $this->em->flush();

        return $cobro;
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