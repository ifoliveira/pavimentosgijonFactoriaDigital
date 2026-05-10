<?php

namespace App\Controller;

use App\Entity\CatalogoProductoConfiguracion;
use App\Form\CatalogoProductoConfiguracionType;
use App\Repository\CatalogoProductoConfiguracionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/catalogo/producto/configuracion')]
class CatalogoProductoConfiguracionController extends AbstractController
{
    #[Route('/', name: 'app_catalogo_producto_configuracion_index', methods: ['GET'])]
    public function index(CatalogoProductoConfiguracionRepository $catalogoProductoConfiguracionRepository): Response
    {
        return $this->render('catalogo_producto_configuracion/index.html.twig', [
            'catalogo_producto_configuracions' => $catalogoProductoConfiguracionRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_catalogo_producto_configuracion_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $catalogoProductoConfiguracion = new CatalogoProductoConfiguracion();
        $form = $this->createForm(CatalogoProductoConfiguracionType::class, $catalogoProductoConfiguracion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($catalogoProductoConfiguracion);
            $entityManager->flush();

            return $this->redirectToRoute('app_catalogo_producto_configuracion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('catalogo_producto_configuracion/new.html.twig', [
            'catalogo_producto_configuracion' => $catalogoProductoConfiguracion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_catalogo_producto_configuracion_show', methods: ['GET'])]
    public function show(CatalogoProductoConfiguracion $catalogoProductoConfiguracion): Response
    {
        return $this->render('catalogo_producto_configuracion/show.html.twig', [
            'catalogo_producto_configuracion' => $catalogoProductoConfiguracion,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_catalogo_producto_configuracion_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CatalogoProductoConfiguracion $catalogoProductoConfiguracion, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CatalogoProductoConfiguracionType::class, $catalogoProductoConfiguracion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_catalogo_producto_configuracion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('catalogo_producto_configuracion/edit.html.twig', [
            'catalogo_producto_configuracion' => $catalogoProductoConfiguracion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_catalogo_producto_configuracion_delete', methods: ['POST'])]
    public function delete(Request $request, CatalogoProductoConfiguracion $catalogoProductoConfiguracion, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$catalogoProductoConfiguracion->getId(), $request->request->get('_token'))) {
            $entityManager->remove($catalogoProductoConfiguracion);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalogo_producto_configuracion_index', [], Response::HTTP_SEE_OTHER);
    }
}
