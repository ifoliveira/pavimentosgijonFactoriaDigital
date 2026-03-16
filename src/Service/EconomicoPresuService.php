<?php
namespace App\Service;

use App\Entity\Economicpresu;
use App\Entity\Presupuestos;
use Doctrine\ORM\EntityManagerInterface;

class EconomicoPresuService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Crea las líneas económicas iniciales de un presupuesto.
     * No hace flush — el controlador es responsable.
     */
    public function iniciarPresu(?float $importeManoObra, Presupuestos $presupuesto): void
    {
        foreach ($presupuesto->getManoObra() as $tipo) {
            if ($tipo->getCoste() != 0 || $tipo->getTipoMo() === 'Otros') {
                $this->alta($tipo->getTipoMo(), (float) ($tipo->getCoste() ?? 0.0), 'D', $presupuesto, 'E');

            }
        }

        $this->alta('Mano de Obra', (float) ($importeManoObra ?? 0.0), 'H', $presupuesto, 'M');
    }

    /**
     * Actualiza el importe pendiente (línea tipo 'T') de un presupuesto.
     * No hace flush — el controlador es responsable.
     */
    public function actualizaResto(float $importePendiente, Presupuestos $presupuesto): void
    {
        $economicpresu = $this->em->getRepository(Economicpresu::class)->findOneBy([
            'idpresuEco' => $presupuesto,
            'aplicaEco'  => 'T',
        ]);

        if (!$economicpresu) {
            return;
        }

        $economicpresu->setImporteEco($importePendiente);
        $this->em->persist($economicpresu);
    }

    private function alta(
        string $concepto,
        float $importe,
        string $debeHaber,
        Presupuestos $presupuesto,
        string $aplica
    ): void {
        $economicpresu = new Economicpresu();
        $economicpresu->setConceptoEco($concepto);
        $economicpresu->setImporteEco($importe);
        $economicpresu->setDebehaberEco($debeHaber);
        $economicpresu->setAplicaEco($aplica);
        $economicpresu->setEstadoEco('1');
        $economicpresu->setIdpresuEco($presupuesto);
        $economicpresu->setTimestamp(new \DateTime());

        $this->em->persist($economicpresu);
    }

    // obtener_restante() eliminado — nunca se llamaba
}