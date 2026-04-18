<?php

namespace App\Service\Documento;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Repository\ClientesRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ManoObra;
use App\Service\Documento\SerieService;
use App\Service\ManoObraService;
use App\Service\Proyecto\ProyectoCalculatorService;

class DocumentoCrearService
{
    public function __construct(
        private ClientesRepository $clientesRepository,
        private EntityManagerInterface $em,
        private SerieService $serieService,
        private ManoObraService $manoObraService,
        private ProyectoCalculatorService $proyectoService,
        
    ) {
    }

    public function crearDocumento(string $tipoDocumento, ?int $clienteId = null): Documento
        {
            $cliente = null;

            if ($clienteId !== null) {
                $cliente = $this->clientesRepository->find($clienteId);

                if (!$cliente instanceof Clientes) {
                    throw new \RuntimeException('Cliente no encontrado.');
                }
            }

            $documento = new Documento();
            $documento->setTipoDocumento($tipoDocumento);
            $documento->setEstadoComercial('borrador');
            $documento->setEstadoCobro('pendiente');
            $documento->setEstadoEjecucion(
                $tipoDocumento === 'ticket' ? 'no_aplica' : 'pendiente'
            );

            if ($cliente) {
                $documento->setCliente($cliente);
            }

            $this->serieService->asignarNumeracion($documento);
            $this->manoObraService->iniciarDocumento($documento);
            if ($documento->getProyecto()) {
                $this->proyectoService->recalcularProyecto($documento->getProyecto());
            }            

            $this->em->persist($documento);
            $this->em->flush();

            return $documento;
        }
      
}