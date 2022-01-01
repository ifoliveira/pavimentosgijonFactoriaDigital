<?php

namespace App\Controller;

use App\Entity\TipoManoObra;
use App\Form\TipoManoObraType;
use App\Repository\TipoManoObraRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("admin/tipo/mano/obra")
 */
class TipoManoObraController extends AbstractController
{
    /**
     * @Route("/", name="tipo_mano_obra_index", methods={"GET"})
     */
    public function index(TipoManoObraRepository $tipoManoObraRepository): Response
    {
        return $this->render('tipo_mano_obra/index.html.twig', [
            'tipo_mano_obras' => $tipoManoObraRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="tipo_mano_obra_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $tipoManoObra = new TipoManoObra();
        $form = $this->createForm(TipoManoObraType::class, $tipoManoObra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($tipoManoObra);
            $entityManager->flush();

            return $this->redirectToRoute('tipo_mano_obra_index');
        }

        return $this->render('tipo_mano_obra/new.html.twig', [
            'tipo_mano_obra' => $tipoManoObra,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="tipo_mano_obra_show", methods={"GET"})
     */
    public function show(TipoManoObra $tipoManoObra): Response
    {
        return $this->render('tipo_mano_obra/show.html.twig', [
            'tipo_mano_obra' => $tipoManoObra,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="tipo_mano_obra_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, TipoManoObra $tipoManoObra): Response
    {
        $form = $this->createForm(TipoManoObraType::class, $tipoManoObra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('tipo_mano_obra_index');
        }

        return $this->render('tipo_mano_obra/edit.html.twig', [
            'tipo_mano_obra' => $tipoManoObra,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="tipo_mano_obra_delete", methods={"POST"})
     */
    public function delete(Request $request, TipoManoObra $tipoManoObra): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tipoManoObra->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($tipoManoObra);
            $entityManager->flush();
        }

        return $this->redirectToRoute('tipo_mano_obra_index');
    }
}
