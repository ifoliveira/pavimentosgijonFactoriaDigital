<?php

namespace App\Controller;

use App\Entity\ManoObra;
use App\Form\ManoObraType;
use App\Form\PresupuestosManoObraType;
use App\Repository\ManoObraRepository;
use App\Repository\PresupuestosRepository;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @Route("/{presu}/new", name="mano_obra_new", methods={"GET","POST"})
     */
    public function new(Request $request, int $presu, PresupuestosRepository $presupuestosRepository): Response
    {
        $manoObra = new ManoObra();

        $entityManager = $this->getDoctrine()->getManager();
        $presupuesto = $presupuestosRepository->findBy(
            ['id' => $presu,],
        );

        $presupuesto[0]->addManoObra($manoObra);
        $form = $this->createForm(PresupuestosManoObraType::class, $presupuesto[0]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           
            $entityManager->persist($manoObra);
            $entityManager->flush();
            $manoObranew = new ManoObra();
            $presupuestonew = $presupuestosRepository->findBy(
                ['id' => $presu,
                ],
            );
            $presupuestonew[0]->addManoObra($manoObranew);
            $form = $this->createForm(PresupuestosManoObraType::class, $presupuestonew[0]);
            $form->handleRequest($request);
            return $this->render('mano_obra/new.html.twig', [
                'mano_obra' => $manoObranew,
                'presupuesto' => $presupuestonew,
                'form' => $form->createView(),
    
            ]);
        }

        return $this->render('mano_obra/new.html.twig', [
            'mano_obra' => $manoObra,
            'presupuesto' => $presupuesto,
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

            return $this->redirectToRoute('presupuestos_show', array('id' => $manoObra->getIdPresu() ));
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

   /**
     * @Route("/delete/fila", name="manoobra_delete_ajax", methods={"GET","POST"})
     */
    public function deleteajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');
        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        // Obtener cesta
        $manoobra = $em->getRepository('App\Entity\ManoObra')->find($datos);
        // Borrado del detalle
        $em->remove($manoobra);
        $em->flush();

        $response = new JsonResponse();

        return $response;
    }      
}
