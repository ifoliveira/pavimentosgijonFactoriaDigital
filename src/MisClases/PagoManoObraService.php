<?php 
namespace App\MisClases;

use App\Entity\Economicpresu;
use App\Entity\Efectivo;
use App\Entity\Tiposmovimiento;
use Doctrine\ORM\EntityManagerInterface;

class PagoManoObraService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function registrarPago(int $presupuestoId, float $importe, string $tipopago): array
    {
        // Buscar el registro pendiente de mano de obra
        $actualizar = $this->em->getRepository(Economicpresu::class)->findOneBy([
            'estadoEco' => 1,
            'aplicaEco' => 'M',
            'idpresuEco' => $presupuestoId
        ]);

        if (!$actualizar) {
            return ['success' => false, 'message' => 'No hay mano de obra pendiente para este presupuesto.'];
        }

        $importeOriginal = $actualizar->getImporteEco();

        if (($importeOriginal - $importe) == 0) {
            $actualizar->setTimestamp(new \DateTime());
            $actualizar->setImporteEco($importe);
            $actualizar->setEstadoEco($tipopago === 'Efectivo' ? 6 : 7);
        } else {
            $actualizar->setImporteEco($importeOriginal - $importe);
            $actualizar->setTimestamp(new \DateTime());

            // Crear nuevo registro con el pago actual
            $nuevo = clone $actualizar;
            $nuevo->setEstadoEco($tipopago === 'Efectivo' ? 6 : 7);
            $nuevo->setImporteEco($importe);
            $this->em->persist($nuevo);
        }

        if ($tipopago === 'Efectivo') {
            $efectivo = new Efectivo();
            $efectivo->setTipoEf(
                $this->em->getRepository(Tiposmovimiento::class)->findOneBy(['descripcionTm' => 'Mano de Obra'])
            );
            $efectivo->setImporteEf($importe);
            $efectivo->setFechaEf(new \DateTime());
            $efectivo->setConceptoEf(
                $actualizar->getConceptoEco() . ' ' . $actualizar->getIdpresuEco()->getClientePe()->getDireccionCl()
            );
            $efectivo->setPresupuestoef($actualizar->getIdpresuEco());
            $this->em->persist($efectivo);
        }

        $this->em->flush();

        return ['success' => true, 'message' => 'Presupuesto actualizado correctamente.'];
    }
}