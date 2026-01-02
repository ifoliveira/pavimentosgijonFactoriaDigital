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
use Doctrine\ORM\EntityManagerInterface;
/**
 * @Route("admin/detallecesta")
 */
class DetallecestaController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }

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
            $this->em->flush();

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

            $this->em->remove($detallecestum);
            $this->em->flush();
        }

        return $this->redirectToRoute('detallecesta_index');
    }

    /**
     * @Route("/delete/fila", name="detallecesta_delete", methods={"GET","POST"})
     */
    public function ajaxcS(Request $request, DetallecestaRepository $detallecestaRepository): jsonResponse
    {
        // Funcion para borrar registro de producto de una cesta determinada
        // Obtener ID del detalle
        $datos = $request->query->get('id');
        
        // Obtener detalle
        $detalle = $this->em->getRepository('App\Entity\Detallecesta')->findOneBy(['id'=> $datos]);

       
        // Borrado del detalle
        $cesta = $detalle->getCestaDc();
        $this->em->remove($detalle);
        $this->em->flush();

        //Volver a crear apartado del loop de la pantalla
        $template =$this->render('productos/loop.html.twig',['cestaId'=>$detalle->getCestaDc()->getId()])->getContent();
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $cestauser = new CestaUser($this->em);


        // Cantidad total de elementos en la cesta
        $cant = $detallecestaRepository->cantotalCesta($detalle->getcestaDc()->getId());
        $cesta->setImporteTotCs($cestauser->getImporteTot($cesta->getId()));
        $this->em->persist($cesta);
        $this->em->flush();

        return $response->setData(['template' => $template, 'cantidad' => $cant]);

    }  

    /**
     * @Route("/nuevo/detalle", name="detallecesta_new", methods={"GET","POST"})
     */
    public function ajaxinscS(Request $request, DetallecestaRepository $detallecestaRepository): jsonResponse
    {

        // Producto y cantidad a añadir
        $producto = $request->query->get('producto');
        $cantidad = $request->query->get('cantidad');
        $importe = $request->query->get('importe');
        $coste = $request->query->get('coste');
        $cestaId = $request->query->get('cesta');
        $texto = $request->query->get('texto');
        $descuento = $request->query->get('descuento');

        if ($texto=="") {
            $texto = NULL;

        }
        
        // Creamos objeto detalle de cesta, con el usuario conectado y los metodos de CestaUser
        $cestaactual = $this->em->getRepository('App\Entity\Cestas')->findOneBy(['id'=> $cestaId]);
        $importeact = $cestaactual->getImporteTotCs();
        $cestaactual->setImporteTotCs($importeact + ($importe * $cantidad));

        $detcesta = new Detallecesta;
        $detcesta->setCestaDc($cestaactual);
        $detcesta->setproductoDc($this->em->getRepository('App\Entity\Productos')->find($producto[0]));
        $detcesta->setCantidadDc($cantidad);
        $detcesta->setPrecioDc($coste);
        $detcesta->setTextoDc($texto);
        $detcesta->setDescuentoDc($descuento);
       // $detcesta->setpvpDc($producto[4]);
        if ($importe != 0){
            $detcesta->setpvpDc($importe); 
        }

        // Insertamos en la tabla el detalle
        $this->em->persist($detcesta);
        $this->em->flush();

        //Volver a crear apartado del loop de la pantalla
        $template =$this->render('productos/loop.html.twig', ['cestaId'=>$cestaId ])->getContent();
        $response = new JsonResponse();
        $response->setStatusCode(200);

         // Cantidad total de elementos en la cesta
        $cant = $detallecestaRepository->cantotalCesta($cestaactual);

        return $response->setData(['template' => $template, 'cantidad' => $cant]);


    }  


    /**
     * @Route("/plusminus/ajax", name="detallecesta_plusminus_ajax", methods={"GET","POST"})
     */
    public function ajaxplumincS(Request $request, DetallecestaRepository $detallecestaRepository): jsonResponse
    {
         
        // Obtener ID del detalle y el detalle a actualizar
        $id = $request->query->get('id');
        $actualizar = $this->em->getRepository('App\Entity\Detallecesta')->findOneBy(['id'=> $id]);

        //actualizamos la cantidad
        $actualizar->setCantidadDc($request->query->get('cantidad'));


        $this->em->flush();

        

        $cestaactual = $this->em->getRepository('App\Entity\Cestas')->findOneBy(['id'=> $actualizar->getCestaDc()->getId() ]);
        $preciototalCesta = $detallecestaRepository->imptotalCesta($cestaactual->getId());
        $cestaactual->setImporteTotCs($preciototalCesta);
        $this->em->persist($cestaactual);
        $this->em->flush();        

        //Volver a crear apartado del loop de la pantalla
        $template =$this->render('productos/loop.html.twig', ['cestaId'=>$actualizar->getCestaDc()->getId()])->getContent();
        $response = new JsonResponse();
        $response->setStatusCode(200);

        // Cantidad total de elementos en la cesta
        $cant = $detallecestaRepository->cantotalCesta($actualizar->getCestaDc()->getId());

        return $response->setData(['template' => $template, 'cantidad' => $cant]);


    }  

}
