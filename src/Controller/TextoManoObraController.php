<?php

namespace App\Controller;

use App\Entity\TextoManoObra;
use App\Form\TextoManoObraType;
use App\Repository\TextoManoObraRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("admin/texto/mano/obra")
 */
class TextoManoObraController extends AbstractController
{
    /**
     * @Route("/", name="texto_mano_obra_index", methods={"GET"})
     */
    public function index(TextoManoObraRepository $textoManoObraRepository): Response
    {
        return $this->render('texto_mano_obra/index.html.twig', [
            'texto_mano_obras' => $textoManoObraRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="texto_mano_obra_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $textoManoObra = new TextoManoObra();
        $form = $this->createForm(TextoManoObraType::class, $textoManoObra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($textoManoObra);
            $entityManager->flush();

            return $this->redirectToRoute('texto_mano_obra_index');
        }

        return $this->render('texto_mano_obra/new.html.twig', [
            'texto_mano_obra' => $textoManoObra,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="texto_mano_obra_show", methods={"GET"})
     */
    public function show(TextoManoObra $textoManoObra): Response
    {
        return $this->render('texto_mano_obra/show.html.twig', [
            'texto_mano_obra' => $textoManoObra,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="texto_mano_obra_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, TextoManoObra $textoManoObra): Response
    {
        $form = $this->createForm(TextoManoObraType::class, $textoManoObra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('texto_mano_obra_index');
        }

        return $this->render('texto_mano_obra/edit.html.twig', [
            'texto_mano_obra' => $textoManoObra,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="texto_mano_obra_delete", methods={"POST"})
     */
    public function delete(Request $request, TextoManoObra $textoManoObra): Response
    {
        if ($this->isCsrfTokenValid('delete'.$textoManoObra->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($textoManoObra);
            $entityManager->flush();
        }

        return $this->redirectToRoute('texto_mano_obra_index');
    }
}
