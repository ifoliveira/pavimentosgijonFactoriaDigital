<?php

namespace App\Controller;

use App\Entity\Detallecesta;
use App\Form\DetallecestaType;
use App\MisClases\CestaUser;
use App\Repository\DetallecestaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("admin/detallecesta")
 */
class DetallecestaController extends AbstractController
{
    /**
     * @Route("/", name="detallecesta_index", methods={"GET"})
     */
    public function index(DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('detallecesta/index.html.twig', [
            'detallecestas' => $detallecestaRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="detallecesta_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $detallecestum = new Detallecesta();
        $form = $this->createForm(DetallecestaType::class, $detallecestum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($detallecestum);
            $entityManager->flush();

            return $this->redirectToRoute('detallecesta_index');
        }

        return $this->render('detallecesta/new.html.twig', [
            'detallecestum' => $detallecestum,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="detallecesta_show", methods={"GET"})
     */
    public function show(Detallecesta $detallecestum): Response
    {
        return $this->render('detallecesta/show.html.twig', [
            'detallecestum' => $detallecestum,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="detallecesta_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Detallecesta $detallecestum): Response
    {
        $form = $this->createForm(DetallecestaType::class, $detallecestum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('detallecesta_index');
        }

        return $this->render('detallecesta/edit.html.twig', [
            'detallecestum' => $detallecestum,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="detallecesta_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Detallecesta $detallecestum): Response
    {
        if ($this->isCsrfTokenValid('delete'.$detallecestum->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($detallecestum);
            $entityManager->flush();
        }

        return $this->redirectToRoute('detallecesta_index');
    }

    /**
     * @Route("/delete/fila", name="detallecesta_delete", methods={"GET","POST"})
     */
    public function ajaxcS(Request $request): jsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del detalle
        $datos = $request->query->get('id');

        // get EntityManager
        $em = $this->getDoctrine()->getManager();
        // Obtener detalle
        $detalle = $em->getRepository('App\Entity\Detallecesta')->find($datos);
        // Borrado del detalle
        $em->remove($detalle);
        $em->flush();

        //Volver a crear apartado del loop de la pantalla
        $template =$this->render('productos/loop.html.twig',['cestaId'=>$detalle->getCestaDc()->getId()])->getContent();
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $cestauser = new CestaUser($em);

        // Cantidad total de elementos en la cesta
        $user = $this->getUser();
        $cant = $cestauser->getCantidadTot($user->getId());

        return $response->setData(['template' => $template, 'cantidad' => $cant]);

    }  

    /**
     * @Route("/nuevo/detalle", name="detallecesta_new", methods={"GET","POST"})
     */
    public function ajaxinscS(Request $request): jsonResponse
    {

        // Funcion encargada de añadir producto a la cesta
        $entityManager = $this->getDoctrine()->getManager();

        // Producto y cantidad a añadir
        $producto = $request->query->get('producto');
        $cantidad = $request->query->get('cantidad');
        $importe = $request->query->get('importe');
        $coste = $request->query->get('coste');
        $cestaId = $request->query->get('cesta');

        
        // Creamos objeto detalle de cesta, con el usuario conectado y los metodos de CestaUser
        $user = $this->getUser();
        $cestauser = new CestaUser($entityManager);

        $detcesta = new Detallecesta;
        $detcesta->setCestaDc($cestauser->getCesta($cestaId));
        $detcesta->setproductoDc($this->getDoctrine()->getRepository('App\Entity\Productos')->find($producto[0]));
        $detcesta->setCantidadDc($cantidad);
        $detcesta->setPrecioDc($coste);
       // $detcesta->setpvpDc($producto[4]);
        if ($importe != 0){
            $detcesta->setpvpDc($importe); 
        }

        // Insertamos en la tabla el detalle
        $entityManager->persist($detcesta);
        $entityManager->flush();

        //Volver a crear apartado del loop de la pantalla
        $template =$this->render('productos/loop.html.twig', ['cestaId'=>$cestaId ])->getContent();
        $response = new JsonResponse();
        $response->setStatusCode(200);

         // Cantidad total de elementos en la cesta
        $cant = $cestauser->getCantidadTot($user->getId());

        return $response->setData(['template' => $template, 'cantidad' => $cant]);


    }  


    /**
     * @Route("/plusminus/ajax", name="detallecesta_plusminus_ajax", methods={"GET","POST"})
     */
    public function ajaxplumincS(Request $request): jsonResponse
    {
        // Funcion encargada de actualizar la cantidad de un producto en el detalle de la cesta
        
        $entityManager = $this->getDoctrine()->getManager();
        
        // Obtener ID del detalle y el detalle a actualizar
        $id = $request->query->get('id');
        $actualizar = $entityManager->getRepository('App\Entity\Detallecesta')->findOneBy(['id'=> $id]);

        //actualizamos la cantidad
        $actualizar->setCantidadDc($request->query->get('cantidad'));
        $entityManager->flush();

        //Volver a crear apartado del loop de la pantalla
        $template =$this->render('productos/loop.html.twig', ['cestaId'=>$actualizar->getCestaDc()->getId()])->getContent();
        $response = new JsonResponse();
        $response->setStatusCode(200);

        // Cantidad total de elementos en la cesta
        $cestauser = new CestaUser($entityManager);
        $user = $this->getUser();
        $cant = $cestauser->getCantidadTot($user->getId());

        return $response->setData(['template' => $template, 'cantidad' => $cant]);


    }  

}
