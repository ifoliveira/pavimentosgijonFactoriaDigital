<?php

namespace App\Service\Documento;



use App\Entity\Documento;
use App\Entity\DocumentoCobro;
use App\Repository\DocumentoCobroRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Proyecto\ProyectoCalculatorService;

class DocumentoCobroService
{
    public function __construct(
        private EntityManagerInterface     $em,
        private DocumentoCobroRepository   $cobroRepo,
        private ProyectoCalculatorService  $proyectoService,
    ) {}

    public function registrar(
        Documento  $documento,
        \DateTime  $fecha,
        string     $metodo,
        string     $importeBruto,
        string     $porcentajeRecargo = '0',
        string     $importeRecargo    = '0',
        string     $importeNeto       = '0',
        ?string    $referencia        = null,
        ?string    $notas             = null,
    ): DocumentoCobro {
        if ($documento->getEstadoCobro() === 'cobrado') {
            throw new \LogicException('La factura ya está completamente cobrada.');
        }

        $cobro = new DocumentoCobro();
        $cobro->setDocumento($documento);
        $cobro->setFecha($fecha);
        $cobro->setMetodo($metodo);
        $cobro->setImporteBruto($importeBruto);
        $cobro->setPorcentajeRecargo($porcentajeRecargo);
        $cobro->setImporteRecargo($importeRecargo);
        $cobro->setImporteNeto($importeNeto);
        $cobro->setReferencia($referencia);
        $cobro->setNotas($notas);

        $this->em->persist($cobro);

        // Pasamos el cobro nuevo directamente
        $this->actualizarEstadoCobro($documento, $cobro);

        if ($documento && $documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }        

        $this->em->persist($documento); // <-- esto faltaba

        $this->em->flush();

        return $cobro;
    }

    public function borrar(int $cobroId): void
    {
        $cobro = $this->cobroRepo->find($cobroId);

        if (!$cobro) {
            throw new \RuntimeException('Cobro no encontrado.');
        }

        $documento = $cobro->getDocumento();
        
        if ($documento && $documento->getProyecto()) {
            $this->proyectoService->recalcularProyecto($documento->getProyecto());
        }        


        $this->em->remove($cobro);

        // Recalcular estado después de borrar
        $this->actualizarEstadoCobro($documento, null, $cobro->getId());
        $this->em->persist($documento);
        $this->em->flush();
    }

    private function actualizarEstadoCobro(    
        Documento        $documento,
        ?DocumentoCobro  $cobroNuevo = null,
        ?int             $cobroExcluidoId = null): void
    {
        $totalCobrado = '0.00';
        foreach ($documento->getCobros() as $c) {
            // Al borrar, excluimos el que acabamos de eliminar
            // porque puede seguir en la colección en memoria
            if ($cobroExcluidoId !== null && $c->getId() === $cobroExcluidoId) {
                continue;
            }
            $totalCobrado = bcadd($totalCobrado, $c->getImporteBruto(), 2);
        }

        // Sumamos el cobro nuevo que aún no está en la colección
        if ($cobroNuevo !== null) {
            $totalCobrado = bcadd($totalCobrado, $cobroNuevo->getImporteBruto(), 2);
        }
        $documento->setTotalCobrado($totalCobrado); //
        $total = $documento->getTotal();

        if (bccomp($totalCobrado, '0.00', 2) === 0) {
            $documento->setEstadoCobro('pendiente');
        } elseif (bccomp($totalCobrado, $total, 2) >= 0) {
            $documento->setEstadoCobro('cobrado');
        } else {
            $documento->setEstadoCobro('parcial');
        }
    }
}