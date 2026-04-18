<?php

namespace App\Controller;

use App\Entity\Clientes;
use App\Entity\Estadocestas;
use App\Entity\Cestas;
use App\Entity\Consultas;
use App\Entity\Presupuestos;
use App\Form\ClientesType;
use App\Form\PresupuestosType;
use App\Form\Clientes2Type;
use App\Repository\ClientesRepository;
use App\Repository\EstadocestasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ManoObraService;
use App\Repository\ConsultasRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints\IsFalse;

#[Route('/admin/clientes')]
class ClientesController extends AbstractController
{


    public function __construct(
        private EntityManagerInterface $em,
        private ManoObraService $manoObraService,
    ) {}


    #[Route('/', name: 'clientes_index', methods: ['GET'])]
    public function index(ClientesRepository $clientesRepository, ConsultasRepository $consultasRepository): Response
    {
        return $this->render('clientes/index.html.twig', [
            'clientes' => $clientesRepository->findAll(),
            'consultas' => $consultasRepository->findBy(array('atencion' => false)),
        ]);
    }

    #[Route('/new', name: 'clientes_new', methods: ['GET','POST'])]
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
            $cesta = new Cestas();
            $user = $this->getUser();
            $cesta->setUserAdmin($user);
            $cesta->setEstadoCs(11);
            $this->em->persist($cesta);
    // Creamos el ticket para el presupuesto
            $presupuesto->setTicket($cesta);
            $this->em->persist($presupuesto);
            $this->em->flush();

            $this->manoObraService->iniciarPresupuesto($presupuesto);
            $this->em->flush();
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

    #[Route('/nuevo', name: 'clientes_nuevo', methods: ['GET', 'POST'])]
    public function nuevo(Request $request, EntityManagerInterface $em): Response
    {
        $cliente = new Clientes();
        $cliente->setTimestampaltaCl(new \DateTime());

        $form = $this->createForm(Clientes2Type::class, $cliente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cliente);
            $em->flush();

            $this->addFlash('success', 'Cliente dado de alta correctamente.');
            return $this->redirectToRoute('clientes_nuevo');
        }

        return $this->render('clientes/nuevo.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'clientes_show', methods: ['GET'])]
    public function show(Clientes $cliente): Response
    {
        return $this->render('clientes/show.html.twig', [
            'cliente' => $cliente,
        ]);
    }

    #[Route('/{id}/edit', name: 'clientes_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Clientes $cliente): Response
    {
        $form = $this->createForm(ClientesType::class, $cliente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('clientes_index');
        }

        return $this->render('clientes/edit.html.twig', [
            'cliente' => $cliente,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'clientes_delete', methods: ['DELETE'])]
    public function delete(Request $request, Clientes $cliente): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cliente->getId(), $request->request->get('_token'))) {
            $this->em->remove($cliente);
            $this->em->flush();
        }

        return $this->redirectToRoute('clientes_index');
    }

 

    #[Route('/delete/consulta', name: 'consulta_delete_ajax', methods: ['GET','POST'])]
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

    #[Route('/hide/consulta', name: 'consulta_hide_ajax', methods: ['GET','POST'])]
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

    #[Route('/clientes/quick-create', name: 'app_clientes_quick_create', methods: ['POST'])]
    public function quickCreate(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $nombre = trim((string) $request->request->get('nombreCl', ''));
        $apellidos = trim((string) $request->request->get('apellidosCl', ''));
        $telefono1 = trim((string) $request->request->get('telefono1Cl', ''));
        $ciudad = trim((string) $request->request->get('ciudadCl', ''));
        $direccion = trim((string) $request->request->get('direccionCl', ''));

        if ($nombre === '') {
            return $this->json([
                'success' => false,
                'message' => 'El nombre es obligatorio.'
            ], 400);
        }

        $cliente = new Clientes();
        $cliente->setNombreCl($nombre);
        $cliente->setApellidosCl($apellidos !== '' ? $apellidos : null);
        $cliente->setTelefono1Cl($telefono1 !== '' ? $telefono1 : null);
        $cliente->setCiudadCl($ciudad !== '' ? $ciudad : null);
        $cliente->setDireccionCl($direccion !== '' ? $direccion : null);

        $entityManager->persist($cliente);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $cliente->getId(),
            'label' => trim($cliente->getNombreCl() . ' ' . ($cliente->getApellidosCl() ?? '')),
        ]);
    }    

}
