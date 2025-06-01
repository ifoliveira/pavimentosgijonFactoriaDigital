<?php

namespace App\Controller;

use App\Entity\Economicpresu;
use App\Form\EconomicpresuType;
use App\Entity\Efectivo;
use App\Repository\EfectivoRepository;
use App\Repository\BancoRepository;
use App\Entity\Tiposmovimiento;
use App\Repository\TiposmovimientoRepository;
use App\Repository\EconomicpresuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/admin/economicpresu")
 */
class EconomicpresuController extends AbstractController
{
    /**
     * @Route("/", name="app_economicpresu_index", methods={"GET"})
     */
    public function index(EconomicpresuRepository $economicpresuRepository): Response
    {
        return $this->render('economicpresu/index.html.twig', [
            'economicpresus' => $economicpresuRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="app_economicpresu_new", methods={"GET", "POST"})
     */
    public function new(Request $request, EconomicpresuRepository $economicpresuRepository): Response
    {
        $economicpresu = new Economicpresu();
        $form = $this->createForm(EconomicpresuType::class, $economicpresu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $economicpresuRepository->add($economicpresu, true);

            return $this->redirectToRoute('app_economicpresu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('economicpresu/new.html.twig', [
            'economicpresu' => $economicpresu,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_economicpresu_show", methods={"GET"})
     */
    public function show(Economicpresu $economicpresu): Response
    {
        return $this->render('economicpresu/show.html.twig', [
            'economicpresu' => $economicpresu,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_economicpresu_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Economicpresu $economicpresu, EconomicpresuRepository $economicpresuRepository): Response
    {
        $form = $this->createForm(EconomicpresuType::class, $economicpresu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $economicpresuRepository->add($economicpresu, true);

            return $this->redirectToRoute('app_economicpresu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('economicpresu/edit.html.twig', [
            'economicpresu' => $economicpresu,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_economicpresu_delete", methods={"POST"})
     */
    public function delete(Request $request, Economicpresu $economicpresu, EconomicpresuRepository $economicpresuRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$economicpresu->getId(), $request->request->get('_token'))) {
            $economicpresuRepository->remove($economicpresu, true);
        }

        return $this->redirectToRoute('app_economicpresu_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @Route("/estado/ajax", name="economic_estado", methods={"GET","POST"})
     */
    public function estadoajax(Request $request): jsonResponse
    {
        $jsonData = array();
        
        $id = $request->query->get('id');
        $estado = $request->query->get('estado');
        $entityManager = $this->getDoctrine()->getManager();
                
        $actualizar = $entityManager->getRepository('App\Entity\Economicpresu')->findOneBy(['id'=> $id]);

        //actualizamos la cantidad
        $actualizar->setestadoEco($estado);
        $entityManager->persist($actualizar);
        $entityManager->flush();

        //Volver a crear apartado del loop de la pantalla
        $jsonData[0]= $id;

        return new jsonResponse($jsonData); 

    }     
    
    /**
     * @Route("/importe/ajax", name="economic_importe", methods={"GET","POST"})
     */
    public function importeajax(Request $request): jsonResponse
    {
        $jsonData = array();
        
        $id = $request->query->get('id');
        $importe = $request->query->get('importe');
        $entityManager = $this->getDoctrine()->getManager();
                
        $actualizar = $entityManager->getRepository('App\Entity\Economicpresu')->findOneBy(['id'=> $id]);

        //actualizamos la cantidad
        $actualizar->setimporteEco($importe);
        $entityManager->persist($actualizar);
        $entityManager->flush();

        //Volver a crear apartado del loop de la pantalla
        $jsonData[0]= $id;

        return new jsonResponse($jsonData); 

    }  
    
    /**
     * @Route("/pagar/ajax", name="economic_pagar", methods={"GET","POST"})
     */
    public function pagarajax(Request $request): jsonResponse
    {
        $jsonData = array();
        
        $id = $request->query->get('id');
        $importe = $request->query->get('importe');
        $entityManager = $this->getDoctrine()->getManager();
        $actualizar = $entityManager->getRepository('App\Entity\Economicpresu')->findOneBy(['id'=> $id]);     

        // Generamos movimiento efectivo
        $efectivo = new Efectivo();
        $efectivo->setTipoEf($entityManager->getRepository('App\Entity\Tiposmovimiento')->findOneBy(['descripcionTm'=> 'Mano de Obra']));
        $efectivo->setImporteEf($importe * -1);
        $efectivo->setFechaEf(new \DateTime());
        $efectivo->setConceptoEf($actualizar->getConceptoEco() . ' ' . $actualizar->getIdpresuEco()->getClientePe()->getDireccionCl());
        $efectivo->setPresupuestoef($actualizar->getIdpresuEco());
        $entityManager->persist($efectivo );
        $entityManager->flush();


        if (($actualizar->getImporteEco() - ($importe)) == 0 || $actualizar->getImporteEco()== 0 || ($actualizar->getImporteEco() - ($importe)) < 0 ) {
            $actualizar->setImporteEco($importe);
            $actualizar->setTimestamp(new \DateTime());
            $actualizar->setEstadoEco(8);    
        } else {
                $actualizar->setImporteEco($actualizar->getImporteEco() - ($importe));
                $actualizar->setTimestamp(new \DateTime());
                $economicnuevo = new economicpresu();
                $economicnuevo = clone $actualizar;
                $economicnuevo->setEstadoEco(8);  
                $economicnuevo->setImporteEco($importe);  
                $entityManager->persist($economicnuevo);   
                          
        };



        $entityManager->persist($actualizar);
        $entityManager->flush();

        //Volver a crear apartado del loop de la pantalla
        $jsonData[0]= $id;

        return new jsonResponse($jsonData); 

    }     

    /**
     * @Route("/conciliar/{id}", name="conciliar_presu", methods={"GET"})
     */
    public function conciliar(economicpresu $economicpresu, BancoRepository $bancoRepository): Response
    {
   
        return $this->render('economicpresu/conciliar.html.twig', [
            'economicpresu' => $economicpresu,
            'bancos' => $bancoRepository->findAll(),
        ]);
    }       

    /**
     * @Route("/{id}/{idbanco}/conciliar", name="economicpresu_conciliar", methods={"GET","POST"})
     */
    public function conciliar_banco(Economicpresu $economicpresu, BancoRepository $bancoRepository, int $idbanco): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $banco = $bancoRepository->findOneBy(array('id' => $idbanco));

        if ($economicpresu->getImporteEco() != $banco->getImporteBn()){

            $banconuevo = clone $banco;  
            $banconuevo->setImporteBn($economicpresu->getImporteEco());
            $importenuevo= $banco->getImporteBn() - $economicpresu->getImporteEco();
            $banco->setImporteBn($importenuevo);
            $economicpresu->setBancoEco($banconuevo);

            $entityManager->persist($banconuevo);

        } else {

            $economicpresu->setBancoEco($banco);    
        }

          

        $entityManager->flush();

        return $this->redirectToRoute('cestas_index');
    }

}
