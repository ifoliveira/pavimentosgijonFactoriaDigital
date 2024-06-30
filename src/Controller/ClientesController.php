<?php

namespace App\Controller;

use App\Entity\Clientes;
use App\Entity\Estadocestas;
use App\Entity\Cestas;
use App\Entity\Consultas;
use App\Entity\Presupuestos;
use App\Form\ClientesType;
use App\Form\PresupuestosType;
use App\Repository\ClientesRepository;
use App\Repository\EstadocestasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\MisClases\ManoObraClass;
use App\MisClases\EconomicoPresu;
use App\Repository\ConsultasRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints\IsFalse;

/**
 * @Route("/admin/clientes")
 */
class ClientesController extends AbstractController
{


    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }


    /**
     * @Route("/", name="clientes_index", methods={"GET"})
     */
    public function index(ClientesRepository $clientesRepository, ConsultasRepository $consultasRepository): Response
    {
        return $this->render('clientes/index.html.twig', [
            'clientes' => $clientesRepository->findAll(),
            'consultas' => $consultasRepository->findBy(array('atencion' => false)),
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
  
            $this->em->persist($cliente);
            $user = $this->getUser();
            $presupuesto->setUserPe($user);
            $presupuesto->setClientePe($cliente);
      // Creamos la cesta para el presupuesto y la señal 
            $user = $this->getUser();
            $cesta = new Cestas();
            $cesta->setUserAdmin($user);
            $cesta->setEstadoCs(11);
            $this->em->persist($cesta);
            $presupuesto->setTicket($cesta);
            $this->em->persist($presupuesto);
            $this->em->flush();

            $manoobra = new ManoObraClass($this->em);
            $manoobra->IniciarPresupuesto($presupuesto);
    

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
            $this->em->remove($cliente);
            $this->em->flush();
        }

        return $this->redirectToRoute('clientes_index');
    }

 

    /**
     * @Route("/delete/consulta", name="consulta_delete_ajax", methods={"GET","POST"})
     */
    public function deleteconsultaajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');
        // Obtener cesta
        $consulta = $this->em->getRepository('App\Entity\Consultas')->find($datos);

        // Borrado del detalle
        $this->em->remove($consulta);
        $this->em->flush();

        $response = new JsonResponse();

        return $response;

    }      

    /**
     * @Route("/hide/consulta", name="consulta_hide_ajax", methods={"GET","POST"})
     */
    public function hideconsultaajax(Request $request): JsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del cesta
        $datos = $request->query->get('id');
        // Obtener cesta
        $consulta = $this->em->getRepository('App\Entity\Consultas')->find($datos);

        // Borrado del detalle

        $consulta->setAtencion(true);
        $this->em->persist($consulta);
        $this->em->flush();

        $response = new JsonResponse();

        return $response;

    }  

}
