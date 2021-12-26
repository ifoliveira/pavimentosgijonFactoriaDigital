<?php

namespace App\Controller;

use App\Entity\Clientes;
use App\Entity\Estadocestas;
use App\Entity\Cestas;
use App\Entity\Presupuestos;
use App\Form\ClientesType;
use App\Form\PresupuestosType;
use App\Repository\ClientesRepository;
use App\Repository\EstadocestasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/admin/clientes")
 */
class ClientesController extends AbstractController
{
    /**
     * @Route("/", name="clientes_index", methods={"GET"})
     */
    public function index(ClientesRepository $clientesRepository): Response
    {
        return $this->render('clientes/index.html.twig', [
            'clientes' => $clientesRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="clientes_new", methods={"GET","POST"})
     */
    public function new(Request $request, EstadocestasRepository $estadocestasRepository): Response
        {   

        $estadocesta=$estadocestasRepository->findOneBy(['id' => 6]);
        $cliente = new Clientes();
        $presupuesto = new Presupuestos();
        $presupuesto->setEstadoPe($estadocesta);
        $cliente->getPresupuestosCl()->add($presupuesto);
        $form = $this->createForm(ClientesType::class, $cliente);
        $form->handleRequest($request);
        


        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($cliente);
            $user = $this->getUser();
            $presupuesto->setUserPe($user);
            $presupuesto->setClientePe($cliente);
      // Creamos la cesta para el presupuesto y la señal 
            $user = $this->getUser();
            $cesta = new Cestas();
            $cesta->setUserCs($user->getId());
            $cesta->setEstadoCs(11);
            $cestasn = new Cestas();
            $cestasn->setUserCs($user->getId());
            $cestasn->setEstadoCs(11);
            $entityManager->persist($cesta);
            $presupuesto->setTicket($cesta);
            $presupuesto->setTicketsnal($cestasn);
            $entityManager->persist($presupuesto);
            $entityManager->flush();
    

            $micarpeta =  $this->getParameter("presupuestoDir") . '/' . $cliente->getNombreCl() . ' ' . $presupuesto->getFechainiPe()->format('Y-m-d') .'/fotos';
            if (!file_exists($micarpeta)) {
                mkdir($micarpeta, 0777, true);
            }


            return $this->redirectToRoute('presupuestos_index');
        }

        return $this->render('clientes/new.html.twig', [
            'cliente' => $cliente,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="clientes_show", methods={"GET"})
     */
    public function show(Clientes $cliente): Response
    {
        return $this->render('clientes/show.html.twig', [
            'cliente' => $cliente,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="clientes_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Clientes $cliente): Response
    {
        $form = $this->createForm(ClientesType::class, $cliente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('clientes_index');
        }

        return $this->render('clientes/edit.html.twig', [
            'cliente' => $cliente,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="clientes_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Clientes $cliente): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cliente->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($cliente);
            $entityManager->flush();
        }

        return $this->redirectToRoute('clientes_index');
    }
}
