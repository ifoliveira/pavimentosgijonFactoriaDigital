<?php

namespace App\Controller;

use App\Entity\Productos;
use App\Entity\Tipoproducto;
use app\Entity\Cestas;
use App\MisClases\CestaUser;
use App\Form\ProductosType;
use App\Repository\CestasRepository;
use App\Repository\ProductosRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\User;

/**
 * @Route("/admin/productos")
 */
class ProductosController extends AbstractController
{
    /**
     * @Route("/", name="productos_index", methods={"GET"})
     */
    public function index(ProductosRepository $productosRepository, CestasRepository $cestasRepository): Response
    {
        $user = $this->getUser();
        $entityManager = $this->getDoctrine()->getManager();
        $tienecesta = $cestasRepository->findBy(
            ['userCs' => $user->getId(),
            'estadoCs' => '1'],
        );

        if (!$tienecesta){ 
            $cesta = new Cestas();
            $cesta->setUserCs($user->getId());
            
            $entityManager->persist($cesta);
            $entityManager->flush();
        };

        $datos = new CestaUser($entityManager);

        return $this->render('productos/index.html.twig', [
            'productos' => $productosRepository->findBy(array('obsoleto' => '0')),
            'cestaId'   => $datos->getCestaUser($user->getId()),
        ]);
    }

    /**
     * @Route("/lista", name="productos_lista", methods={"GET"})
     */
    public function listapd(ProductosRepository $productosRepository): Response
    {
        return $this->render('productos/lista.html.twig', [
            'productos' => $productosRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="productos_new", methods={"GET","POST"})
     */
    public function new(Request $request , CestasRepository $cestasRepository): Response
    {
        $producto = new Productos();
        $form = $this->createForm(ProductosType::class, $producto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($producto);
            $entityManager->flush();

            return $this->redirectToRoute('productos_index');
        }

        return $this->render('productos/new.html.twig', [
            'producto' => $producto,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="productos_show", methods={"GET"})
     */
    public function show(Productos $producto): Response
    {
        return $this->render('productos/show.html.twig', [
            'producto' => $producto,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="productos_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Productos $producto): Response
    {
        $form = $this->createForm(ProductosType::class, $producto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('productos_index');
        }

        return $this->render('productos/edit.html.twig', [
            'producto' => $producto,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="productos_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Productos $producto): Response
    {
        if ($this->isCsrfTokenValid('delete'.$producto->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($producto);
            $entityManager->flush();
        }

        return $this->redirectToRoute('productos_index');
    }
}
