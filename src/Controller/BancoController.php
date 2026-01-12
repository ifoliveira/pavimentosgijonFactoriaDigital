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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;



/**
 * @Route("/admin/banco")
 */
class BancoController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }


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
            $this->em->persist($banco);
            $this->em->flush();

            return $this->redirectToRoute('banco_index');
        }

        return $this->render('banco/new.html.twig', [
            'banco' => $banco,
            'form' => $form->createView(),
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
            $this->em->flush();

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
            
            $this->em->remove($banco);
            $this->em->flush();
        }

        return $this->redirectToRoute('banco_index');
    }

    /**
     * @Route("/{id}/transferencia", name="banco_transferencia", methods={"GET","POST"})
     */
    public function conciliar(Request $request, Banco $banco,  EfectivoRepository $efectivoRepository, TiposmovimientoRepository $tiposmovimientoRepository): Response
    {
                
        // Buscamos la descripcion del tipo        
        $tipo = $this->em->getRepository(Tiposmovimiento::class)->findOneBy(
            ['descripcionTm' => 'Transferencia']
            );

        // Creamos un movimiento en efectivo
        $efectivo = new Efectivo();
        $efectivo->setTipoEf($tipo);
        $efectivo->setConceptoEf("Retirada Cajero");
        $efectivo->setImporteEf($banco->getImporteBn() * -1);
        $efectivo->setFechaEf(new \DateTime());    

        $this->em->persist($efectivo);

        // Si existe la movemos a objeto banco e insertamo solo banco        
        $banco->setCategoriaBn($tipo);
        $this->em->flush();

        return $this->redirectToRoute('banco_index');
    }

    



}
