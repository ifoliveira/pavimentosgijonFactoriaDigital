<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Repository\DocumentoRepository;

class DocumentoVerService
{
    public function __construct(
        private DocumentoRepository $documentoRepository,
    ) {
    }

    public function obtenerPorId(int $id): ?Documento
    {
        return $this->documentoRepository->find($id);
    }
}