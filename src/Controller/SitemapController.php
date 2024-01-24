<?php

namespace App\Controller;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{

    #[Route('/sitemap.xml', name: 'sitemap')]
    public function index()
    {
        // find published blog posts from db

        $response = new Response(
            $this->renderView('./sitemaps/sitemap.html.twig'),
            200
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}