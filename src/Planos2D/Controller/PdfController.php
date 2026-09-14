<?php

namespace App\Planos2D\Controller;

use App\Planos2D\DTO\PlanoPdfData;
use App\Planos2D\Service\PlanoPdfGenerator;
use App\Planos2D\Service\PlanoPortableValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PdfController extends AbstractController
{
    public function __construct(
        private readonly PlanoPdfGenerator $planoPdfGenerator,
        private readonly PlanoPortableValidator $planoPortableValidator,
    ) {
    }

    #[Route('/planos2d/pdf', name: 'planos2d_pdf', methods: ['POST'])]
    public function generar(Request $request): Response
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload)) {
                throw new \InvalidArgumentException('La peticion debe ser un objeto JSON.');
            }

            $plano = $payload['plano'] ?? null;
            $svg = $payload['svg'] ?? null;
            $svgDimensiones = $payload['svgDimensiones'] ?? null;
            $svgDistancias = $payload['svgDistancias'] ?? null;

            $this->planoPortableValidator->validar($plano);

            if (!is_string($svg)) {
                throw new \InvalidArgumentException('El SVG del plano es obligatorio.');
            }

            $datos = PlanoPdfData::fromArray(is_array($payload['datos'] ?? null) ? $payload['datos'] : []);
            $orientacion = is_string($payload['orientacion'] ?? null) ? $payload['orientacion'] : null;
            $pdf = $this->planoPdfGenerator->generar(
                $svg,
                $datos,
                $orientacion,
                is_string($svgDistancias) ? $svgDistancias : null,
                is_string($svgDimensiones) ? $svgDimensiones : null
            );

            return new Response($pdf, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="plano-bano.pdf"',
            ]);
        } catch (\JsonException) {
            return $this->error('El cuerpo de la peticion no contiene JSON valido.');
        } catch (\InvalidArgumentException $error) {
            return $this->error($error->getMessage());
        }
    }

    private function error(string $mensaje): JsonResponse
    {
        return new JsonResponse(['error' => $mensaje], Response::HTTP_BAD_REQUEST);
    }
}
