<?php

namespace App\Service\Documento;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Repository\ClientesRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ProyectoRepository;
use App\Repository\DocumentoRepository;

class DocumentoCabeceraService
{
    public function __construct(
        private DocumentoRepository $documentoRepository,
        private ClientesRepository $clientesRepository,
        private ProyectoRepository $proyectoRepository,
        private EntityManagerInterface $em
    ) {
    }

    public function guardarCabecera(int $documentoId, array $data): void
    {
        $documento = $this->documentoRepository->find($documentoId);

        if (!$documento) {
            throw new \RuntimeException('Documento no encontrado.');
        }

        $cliente = null;
        if (!empty($data['clienteId'])) {
            $cliente = $this->clientesRepository->find($data['clienteId']);
            if (!$cliente) {
                throw new \RuntimeException('Cliente no encontrado.');
            }
        }

        $proyecto = null;
        if (!empty($data['proyectoId'])) {
            $proyecto = $this->proyectoRepository->find($data['proyectoId']);
            if (!$proyecto) {
                throw new \RuntimeException('Proyecto no encontrado.');
            }
        }

        $documento->setCliente($cliente);
        $documento->setProyecto($proyecto);
        $documento->setNotas($data['notas'] ?? null);

        if (!empty($data['fechaEmision'])) {
            $documento->setFechaEmision(new \DateTime($data['fechaEmision']));
        }

        $this->em->flush();
    }
}