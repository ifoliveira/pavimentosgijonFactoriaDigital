<?php

namespace App\Controller;

use App\Entity\Efectivo;
use App\Form\EfectivoType;
use App\Repository\EfectivoRepository;
use App\Repository\DetallecestaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/admin/efectivo")
 */
class EfectivoController extends AbstractController
{
    /**
     * @Route("/", name="efectivo_index", methods={"GET"})
     */
    public function index(EfectivoRepository $efectivoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('efectivo/index.html.twig', [
            'efectivos' => $efectivoRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="efectivo_new", methods={"GET","POST"})
     */
    public function new(Request $request, DetallecestaRepository $detallecestaRepository): Response
    {
        $efectivo = new Efectivo();
        $form = $this->createForm(EfectivoType::class, $efectivo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager = $this->getDoctrine()->getManager();

            $entityManager->persist($efectivo);
            $entityManager->flush();

            return $this->redirectToRoute('efectivo_index');
        }

        return $this->render('efectivo/new.html.twig', [
            'efectivo' => $efectivo,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="efectivo_show", methods={"GET"})
     */
    public function show(Efectivo $efectivo , DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('efectivo/show.html.twig', [
            'efectivo' => $efectivo,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="efectivo_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Efectivo $efectivo , DetallecestaRepository $detallecestaRepository): Response
    {
        $form = $this->createForm(EfectivoType::class, $efectivo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('efectivo_index');
        }

        return $this->render('efectivo/edit.html.twig', [
            'efectivo' => $efectivo,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="efectivo_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Efectivo $efectivo): Response
    {
        if ($this->isCsrfTokenValid('delete'.$efectivo->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($efectivo);
            $entityManager->flush();
        }

        return $this->redirectToRoute('efectivo_index');
    }

    /**
     * @Route("/delete/fila", name="efectivo_delete_ajax", methods={"GET","POST"})
     */
    public function deleteajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una efectivo determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');

        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        // Obtener cesta
        $efectivo = $em->getRepository('App\Entity\Efectivo')->find($datos);
        if (is_object($efectivo)){
        // Borrado del detalle
            
            $em->remove($efectivo);
            $em->flush();

        }


        $response = new JsonResponse();

        return $response;

    }     
}
