<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PostRepository;
use App\Repository\ConsultasRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Consultas;
use App\Form\ConsultasType;
use DateTime;


class BlogController extends AbstractController
{

    /**
     * @Route("/reforma-bano-gijon/{slug}", name="blog_show")
     */
    public function show(string $slug, PostRepository $postRepository, ConsultasRepository $consultasRepository, Request $request): Response
    {
        $post = $postRepository->findOneBy(['slug' => $slug]);

        if (!$post) {
            throw $this->createNotFoundException('El post solicitado no existe');
        }

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


        return $this->render('blog/show.html.twig', [
            'post' => $post,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/reforma-bano-gijon/casa/blog", name="blog")
     */
    public function mainBlog(PostRepository $postRepository): Response
    {

        $posts = $postRepository->findBy([], ['publishedAt' => 'DESC']);
    
            return $this->render('blog/index.html.twig', [
                'posts' => $posts,
            ]);

    }    
}
