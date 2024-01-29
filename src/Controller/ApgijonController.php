<?php

namespace App\Controller;

use App\Entity\Consultas;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Form\LogsType;
use App\Entity\Logs;
use App\Form\ConsultasType;
use App\Repository\ConsultasRepository;
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
        
        return $this->render('apgijon/principal.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView(),
            'cookies'=> 'No mostrar'
        ]);
    }

    /**
     * @Route("/nosotros", name="aboutus")
     */
    public function aboutus(Request $request): Response
    {
        return $this->render('apgijon/nosotros.html.twig', [
            'controller_name' => 'ApgijonController',
        ]);
    } 

    /**
     * @Route("/reforma-integral-banos-gijon", name="integral")
     */
    public function integral(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/integral.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }     

    /**
     * @Route("/reforma-bano-gijon/platosdeducha", name="blogplato")
     */
    public function blogplato(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/blogplato.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }     


    /**
     * @Route("/reforma-bano-gijon/tendencias", name="tendencias")
     */
    public function blogtendencias(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/tendencia.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()

        ]);
    }       

    /**
     * @Route("/reforma-bano-gijon/blog", name="blog")
     */
    public function blog(Request $request): Response
    {
        
        return $this->render('apgijon/blog.html.twig', [
            'controller_name' => 'ApgijonController'

        ]);
    } 

    /**
     * @Route("/cambio-banera-ducha-gijon", name="platoducha")
     */
    public function platoducha(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/platoducha.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()
        ]);
    }   

    /**
     * @Route("/mamaparas-bano-gijon", name="mampara")
     */
    public function mamparas(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('integral', [], Response::HTTP_SEE_OTHER);
        }   

        return $this->render('apgijon/mamparas.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView()
        ]);
    }       

    /**
     * @Route("/contacto", name="contacto")
     */
    public function contacto(Request $request, ConsultasRepository $consultasRepository): Response
    {

        $consulta = new Consultas();

        $form = $this->createForm(ConsultasType::class, $consulta);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $consulta = $form->getData();
  
            $consulta->setTimestamp(New DateTime());
            $consulta->setatencion(false);

            $consultasRepository->add($consulta, true);

            return $this->redirectToRoute('contacto', [], Response::HTTP_SEE_OTHER);
        }        

        return $this->render('apgijon/contacto.html.twig', [
            'controller_name' => 'ApgijonController',
            'form' => $form->createView(),
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
