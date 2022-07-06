<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApgijonController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index(): Response
    {
        return $this->render('apgijon/index.html.twig', [
            'controller_name' => 'ApgijonController',
        ]);
    }

    /**
     * @Route("/img-route/{img}", name="img_route")
     * A route with one parameter
     */
    public function imagen($img): Response
    {
        //Retrieve the root folder with the kernel and then add the location of the 
        //file
        $filename = $this->getParameter('kernel.project_dir') . '/public_html/img/' . $img;
        //If the file exists then we return it, otherwise return 404
 
        if (file_exists($filename)) {
            //return a new BinaryFileResponse with the file name
            return new BinaryFileResponse($filename);
        } else {
            return new JsonResponse(null, 404);
        }
    }

}
