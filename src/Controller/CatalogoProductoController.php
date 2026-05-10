<?php

namespace App\Controller;

use App\Entity\CatalogoProducto;
use App\Form\CatalogoProductoType;
use App\Repository\CatalogoProductoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/catalogo/producto')]
class CatalogoProductoController extends AbstractController
{
    #[Route('/', name: 'app_catalogo_producto_index', methods: ['GET'])]
    public function index(CatalogoProductoRepository $catalogoProductoRepository): Response
    {
        return $this->render('catalogo_producto/index.html.twig', [
            'catalogo_productos' => $catalogoProductoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_catalogo_producto_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $catalogoProducto = new CatalogoProducto();
        $form = $this->createForm(CatalogoProductoType::class, $catalogoProducto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($catalogoProducto);
            $entityManager->flush();

            return $this->redirectToRoute('app_catalogo_producto_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('catalogo_producto/new.html.twig', [
            'catalogo_producto' => $catalogoProducto,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_catalogo_producto_show', methods: ['GET'])]
    public function show(CatalogoProducto $catalogoProducto): Response
    {
        return $this->render('catalogo_producto/show.html.twig', [
            'catalogo_producto' => $catalogoProducto,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_catalogo_producto_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CatalogoProducto $catalogoProducto, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CatalogoProductoType::class, $catalogoProducto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_catalogo_producto_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('catalogo_producto/edit.html.twig', [
            'catalogo_producto' => $catalogoProducto,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_catalogo_producto_delete', methods: ['POST'])]
    public function delete(Request $request, CatalogoProducto $catalogoProducto, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$catalogoProducto->getId(), $request->request->get('_token'))) {
            $entityManager->remove($catalogoProducto);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalogo_producto_index', [], Response::HTTP_SEE_OTHER);
    }
}
