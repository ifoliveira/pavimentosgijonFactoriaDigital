<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Form\LogsType;
use App\Entity\Logs;
use Symfony\Component\HttpFoundation\Request;

class ApgijonController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index(Request $request): Response
    {

        // creates a task object and initializes some data for this example
        $log = new Logs(); 
        $log->setDescripcion('');

        $form = $this->createForm(LogsType::class, $log);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $log = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $log->setIdLog(1);
            $log->setFecha(new \DateTime());
    
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $entityManager->persist($log);
    
            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
            // ... perform some action, such as saving the task to the database

            return $this->render('apgijon/index.html.twig', [
                'controller_name' => 'ApgijonController',
                'form' => $form->createView(),
                'cookies'=> 'Mostrar'
            ]);
        }
        
        return $this->render('apgijon/index.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView(),
            'cookies'=> 'No mostrar'
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
