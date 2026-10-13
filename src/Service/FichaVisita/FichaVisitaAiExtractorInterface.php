<?php

namespace App\Service\FichaVisita;

use App\Dto\FichaVisita\FichaVisitaDatos;

interface FichaVisitaAiExtractorInterface
{
    public function extraer(string $absoluteImagePath, string $mimeType): FichaVisitaDatos;
}
