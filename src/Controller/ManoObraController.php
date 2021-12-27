<?php

namespace App\Controller;

use App\Entity\ManoObra;
use App\Form\ManoObraType;
use App\Repository\ManoObraRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/mano/obra")
 */
class ManoObraController extends AbstractController
{
    /**
     * @Route("/", name="mano_obra_index", methods={"GET"})
     */
    public function index(ManoObraRepository $manoObraRepository): Response
    {
        return $this->render('mano_obra/index.html.twig', [
            'mano_obras' => $manoObraRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="mano_obra_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $manoObra = new ManoObra();
        $form = $this->createForm(ManoObraType::class, $manoObra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($manoObra);
            $entityManager->flush();

            return $this->redirectToRoute('mano_obra_index');
        }

        return $this->render('mano_obra/new.html.twig', [
            'mano_obra' => $manoObra,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="mano_obra_show", methods={"GET"})
     */
    public function show(ManoObra $manoObra): Response
    {
        return $this->render('mano_obra/show.html.twig', [
            'mano_obra' => $manoObra,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="mano_obra_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, ManoObra $manoObra): Response
    {
        $form = $this->createForm(ManoObraType::class, $manoObra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('mano_obra_index');
        }

        return $this->render('mano_obra/edit.html.twig', [
            'mano_obra' => $manoObra,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="mano_obra_delete", methods={"POST"})
     */
    public function delete(Request $request, ManoObra $manoObra): Response
    {
        if ($this->isCsrfTokenValid('delete'.$manoObra->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($manoObra);
            $entityManager->flush();
        }

        return $this->redirectToRoute('mano_obra_index');
    }
}
