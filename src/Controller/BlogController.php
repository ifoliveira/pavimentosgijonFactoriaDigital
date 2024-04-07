<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PostRepository;
use App\Repository\ConsultasRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Consultas;
use App\Entity\Image;
use App\Form\ConsultasType;
use DateTime;
use App\Entity\Post;
use App\Form\PostType;
use App\Form\pdfUploadType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class BlogController extends AbstractController
{


    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }



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
     * @Route("/reforma-bano-gijon/", name="blog")
     */
    public function mainBlog(PostRepository $postRepository): Response
    {

        $posts = $postRepository->findBy(['isPublished' => true], ['publishedAt' => 'DESC']);
    
            return $this->render('blog/index.html.twig', [
                'posts' => $posts,
            ]);

    }    
    /**
     * @Route("/admin/post/new", name="post_new")
     */
        public function new(Request $request): Response
        {
            $post = new Post();
            $form = $this->createForm(PostType::class, $post);
            $form->handleRequest($request);
    
            if ($form->isSubmitted() && $form->isValid()) {
                $image= New Image();
                $image->setFilePath($post->getSlug() . '1100x600.webp');
                $image->setImageType("header");
                $image->setAlt($post->getHeaderH1());

                $image2= New Image();
                $image2->setFilePath($post->getSlug() . '600x450.webp');
                $image2->setImageType("blog");
                $image2->setAlt($post->getHeaderH1());


                $post->addImage($image);
                $post->addImage($image2);
                $this->em->persist($post);
                $this->em->flush();
    
                
            }
    
            return $this->render('blog/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }

    /**
     * @Route("/admin/post/upload", name="upload_img")
     */
        public function upload(Request $request): Response
        {
            $form = $this->createForm(pdfUploadType::class);
            $form->handleRequest($request);
    
            if ($form->isSubmitted() && $form->isValid()) {
                $file = $form->get('pdfFile')->getData();
                
     
                if ($file) {
                    $targetDirectory = $this->getParameter('kernel.project_dir').'/public_html/Light-HTML/img/blog';
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $originalFilename . '.' . $file->guessExtension();
    
                    try {
                        $file->move($targetDirectory, $fileName);
                        // Puedes agregar aquí un mensaje de éxito o redirección
                    } catch (FileException $e) {
                        // Manejar error
                    }
                }
            }
    
            return $this->render('blog/upload.html.twig', [
                'form' => $form->createView(),
            ]);
        }

}
