<?php

namespace App\Controller;

use App\Repository\BancoReferenciasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PagosRepository;
use App\Repository\BancoRepository;
use App\MisClases\GenerarPago;
use App\Entity\Pagos;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/admin/pagos")
 */
class PagosController extends AbstractController
{

    protected $em;

    public function __construct( EntityManagerInterface $em )
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="pagos_index", methods={"GET"})
     */
    public function index(PagosRepository $pagosRepository, BancoRepository $bancoRepository): Response
    {
        return $this->render('pagos/index.html.twig', [
            'pagos'  => $pagosRepository->findBy(['tipoPg' => 'Tarjeta', 'bancoPg' => null]),
            'bancos' => $bancoRepository->findBy(['categoria_Bn' => '3', 'conciliado' => null]),
        ]);
    }    

    /**
     * @Route("/conciliar/{id}", name="pagos_conciliar", methods={"GET"})
     */
    public function conciliarPagos(Request $request, Pagos $pagos, BancoRepository $bancoRepository): JsonResponse
    {    
        $bancoId = $request->query->get('banco');

        $generarPago = New GenerarPago($this->em);

        $generarPago->conciliar($pagos, $bancoId, $bancoRepository);

        $response = new JsonResponse();

        return $response;

    }
     

}