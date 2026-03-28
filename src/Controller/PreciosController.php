<?php

namespace App\Controller;

use App\Entity\Precio;
use App\Repository\PrecioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/precios', name: 'admin_precios_')]
class PreciosController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(PrecioRepository $repo): Response
    {
        return $this->render('precios/index.html.twig', [
            'precios' => $repo->getPorGrupoYTipo(),
        ]);
    }

    #[Route('/{id}/editar', name: 'editar', methods: ['GET', 'POST'])]
    public function editar(
        Precio $precio,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $valor = $request->request->get('valor');
            if (is_numeric($valor) && $valor >= 0) {
                $precio->setValor((float) $valor);
                $em->flush();
                $this->addFlash('success', 'Precio actualizado correctamente.');
            } else {
                $this->addFlash('error', 'El valor introducido no es válido.');
            }
            return $this->redirectToRoute('admin_precios_index');
        }

        return $this->render('precios/editar.html.twig', [
            'precio' => $precio,
        ]);
    }

  
}