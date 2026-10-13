<?php

namespace App\Service\FichaVisita;

use App\Dto\FichaVisita\FichaVisitaDatos;
use App\Service\OpenAIClient;

final class OpenAiFichaVisitaExtractor implements FichaVisitaAiExtractorInterface
{
    public function __construct(private readonly OpenAIClient $openAIClient)
    {
    }

    public function extraer(string $absoluteImagePath, string $mimeType): FichaVisitaDatos
    {
        $json = $this->openAIClient->interpretarFichaVisitaImagen($absoluteImagePath, $mimeType);

        return FichaVisitaDatos::fromArray($json ?? []);
    }
}
