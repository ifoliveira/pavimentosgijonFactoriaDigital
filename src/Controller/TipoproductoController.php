<?php

namespace App\Controller;

use App\Entity\Tipoproducto;
use App\Form\TipoproductoType;
use App\Repository\TipoproductoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tipoproducto")
 */
class TipoproductoController extends AbstractController
{
    /**
     * @Route("/", name="tipoproducto_index", methods={"GET"})
     */
    public function index(TipoproductoRepository $tipoproductoRepository): Response
    {
        return $this->render('tipoproducto/index.html.twig', [
            'tipoproductos' => $tipoproductoRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="tipoproducto_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $tipoproducto = new Tipoproducto();
        $form = $this->createForm(TipoproductoType::class, $tipoproducto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($tipoproducto);
            $entityManager->flush();

            return $this->redirectToRoute('tipoproducto_index');
        }

        return $this->render('tipoproducto/new.html.twig', [
            'tipoproducto' => $tipoproducto,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="tipoproducto_show", methods={"GET"})
     */
    public function show(Tipoproducto $tipoproducto): Response
    {
        return $this->render('tipoproducto/show.html.twig', [
            'tipoproducto' => $tipoproducto,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="tipoproducto_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Tipoproducto $tipoproducto): Response
    {
        $form = $this->createForm(TipoproductoType::class, $tipoproducto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('tipoproducto_index');
        }

        return $this->render('tipoproducto/edit.html.twig', [
            'tipoproducto' => $tipoproducto,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="tipoproducto_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Tipoproducto $tipoproducto): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tipoproducto->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($tipoproducto);
            $entityManager->flush();
        }

        return $this->redirectToRoute('tipoproducto_index');
    }
}
