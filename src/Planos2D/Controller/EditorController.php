<?php

namespace App\Planos2D\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class EditorController extends AbstractController
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    #[Route('/planos2d/editor', name: 'planos2d_editor', methods: ['GET'])]
    public function editor(): Response
    {
        return $this->render('planos2d/editor.html.twig');
    }

    #[Route('/planos2d/assets/{path}', name: 'planos2d_asset', requirements: ['path' => '.+'], methods: ['GET'])]
    public function asset(string $path): BinaryFileResponse
    {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            throw new NotFoundHttpException();
        }

        $filePath = $this->projectDir . '/assets/planos2d/' . $path;
        $realPath = realpath($filePath);
        $assetsRoot = realpath($this->projectDir . '/assets/planos2d');

        if (
            $realPath === false
            || $assetsRoot === false
            || ($realPath !== $assetsRoot && !str_starts_with($realPath, $assetsRoot . DIRECTORY_SEPARATOR))
        ) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($realPath);
        $extension = pathinfo($realPath, PATHINFO_EXTENSION);

        if ($extension === 'js') {
            $response->headers->set('Content-Type', 'application/javascript; charset=UTF-8');
        }

        if ($extension === 'css') {
            $response->headers->set('Content-Type', 'text/css; charset=UTF-8');
        }

        return $response;
    }
}
