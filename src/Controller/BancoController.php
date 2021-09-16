<?php

namespace App\Controller;

use App\Entity\Banco;
use App\Entity\Efectivo;
use App\Entity\Tiposmovimiento;
use App\Form\Banco1Type;
Use App\MisClases\Banks_N43;
use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\EfectivoRepository;
use App\Repository\TiposmovimientoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/admin/banco")
 */
class BancoController extends AbstractController
{
    /**
     * @Route("/", name="banco_index", methods={"GET"})
     */
    public function index(BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('banco/index.html.twig', [
            'bancos' => $bancoRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="banco_new", methods={"GET","POST"})
    */   
    public function new(Request $request,DetallecestaRepository $detallecestaRepository): Response
    {
        $banco = new Banco();
        $form = $this->createForm(Banco1Type::class, $banco);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($banco);
            $entityManager->flush();

            return $this->redirectToRoute('banco_index');
        }

        return $this->render('banco/new.html.twig', [
            'banco' => $banco,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/N43", name="N43")
     */
    public function N43(BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        $directorio = $this->getParameter("c43Dir");
        $fichero = file_get_contents($directorio .'/C43.txt');
       
        // informamos cabeceara del ficheor csv 
        $datosC43 = new Banks_N43();

        $datosC43->parse($fichero);

        foreach ($datosC43->accounts as $cuentas){
            foreach ($cuentas->entries as $valor){
        // Buscamos la descripcion del tipo        
                 $entityManager = $this->getDoctrine()->getManager();
                 $tipo = $entityManager->getRepository(Tiposmovimiento::class)->findOneBy(
                    ['descripcionTm' => $valor->banco->getCategoriaBn()->getdescripcionTm()]
                );

        // Si existe la movemos a objeto banco e insertamo solo banco        
                if ($tipo) {
                    $valor->banco->setCategoriaBn($tipo);
                }else{
        // Si no existe la insertamos tambien en el tipo            
                   $entityManager->persist($valor->banco->getCategoriaBn());
                }

                 $entityManager->persist($valor->banco);
                 $entityManager->flush();
            } 
        }

        

        // Borra el fichero
            unlink($directorio .'/C43.txt');

       return $this->render('banco/index.html.twig', [
        'bancos' => $bancoRepository->findAll(),
         ]);

    }

    /**
     * @Route("/{id_Bn}", name="banco_show", methods={"GET"})
     */
    public function show(Banco $banco, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('banco/show.html.twig', [
            'banco' => $banco,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="banco_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Banco $banco , DetallecestaRepository $detallecestaRepository): Response
    {
        $form = $this->createForm(Banco1Type::class, $banco);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('banco_index');
        }

        return $this->render('banco/edit.html.twig', [
            'cesta' => $banco,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id_Bn}", name="banco_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Banco $banco, DetallecestaRepository $detallecestaRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$banco->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($banco);
            $entityManager->flush();
        }

        return $this->redirectToRoute('banco_index');
    }

    /**
     * @Route("/{id}/transferencia", name="banco_transferencia", methods={"GET","POST"})
     */
    public function conciliar(Request $request, Banco $banco,  EfectivoRepository $efectivoRepository, TiposmovimientoRepository $tiposmovimientoRepository): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        
        // Buscamos la descripcion del tipo        
        $tipo = $entityManager->getRepository(Tiposmovimiento::class)->findOneBy(
            ['descripcionTm' => 'Transferencia']
            );

        // Creamos un movimiento en efectivo
        $efectivo = new Efectivo();
        $efectivo->setTipoEf($tipo);
        $efectivo->setConceptoEf("Retirada Cajero");
        $efectivo->setImporteEf($banco->getImporteBn() * -1);
        $efectivo->setFechaEf(new \DateTime());    

        $entityManager->persist($efectivo);

        // Si existe la movemos a objeto banco e insertamo solo banco        
        $banco->setCategoriaBn($tipo);
        $entityManager->flush();

        return $this->redirectToRoute('banco_index');
    }



}
