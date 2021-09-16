<?php

namespace App\Controller;

use App\Repository\BancoRepository;
Use App\MisClases\Banks_N43;
use App\Entity\Banco;
use App\Entity\Cestas;
use App\MisClases\Importar_Ticket;
use App\MisClases\Meteo;
use App\Repository\DetallecestaRepository;
use App\Repository\CestasRepository;
use App\Repository\EfectivoRepository;
use App\Repository\ForecastRepository;
use App\Repository\TiposmovimientoRepository;
use Doctrine\ORM\Mapping\Id;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class AdminProController extends AbstractController
{
    /**
     * @Route("/admin", name="admin_pro")
     */
    public function index(TiposmovimientoRepository $tiposmovimientoRepository, BancoRepository $bancoRepository, CestasRepository $cestasRepository, EfectivoRepository $efectivoRepository, ForecastRepository $forecastRepository): Response
    {
        $meteo = new Meteo;
        $iconweather = $meteo->icon();

        $bancototal = $bancoRepository->totalBanco();
        $ventasmestotal= $bancoRepository->ventamesBanco();
        $ventaefetotal = $cestasRepository->ventaefemes();
        $efectivototal = $efectivoRepository->totalefectivo();
        $manoobratotal = $tiposmovimientoRepository->findBy(
            ['descripcionTm' => 'Mano de Obra'],
        );
        $forecast = $forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC'],
        );

        $forecast = $forecastRepository->findBy(
            ['estadoFr' => 'P'],
            ['fechaFr' => 'ASC']
        );


        return $this->render('admin_pro/index.html.twig', [
            'controller_name' => 'AdminProController',
            'bancototal' => $bancototal,
            'ventastotal' => $ventasmestotal,
            'ventaefetotal' => $ventaefetotal,
            'gastos' => $efectivototal,
            'forecast' => $forecast,
            'manoobratotal' => $manoobratotal,
            'weathericon' => $iconweather 

        ]);
    }

    /**
     * @Route("/admin/C43", name="insert_C43", methods={"GET","POST"})
     */
    public function contactAction(Request $request , DetallecestaRepository $detallecestaRepository)
    {
        $directorio = $this->getParameter("c43Dir");

        $defaultData = array('message' => 'Escribe un mensaje aquí');

        $form = $this->createFormBuilder($defaultData)
            ->add('fichero_C43', FileType::class)
            ->getForm();
    
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Los datos están en un array con los keys "name", "email", y "message"
            $data = $form->getData();

                try {
                              $data['fichero_C43']->move($directorio, "C43.txt");
                          } catch (FileException $e) {
                            // unable to upload the photo, give up
                         }
            return $this->redirectToRoute('insert_C43');
        }

        $nombrefic = $directorio . '/C43.txt';

        if (file_exists($nombrefic)) {
            $fichero = file_get_contents($nombrefic);
       
            // informamos cabeceara del ficheor csv 
            $datosC43 = new Banks_N43();
        
            $datosC43->parse($fichero);

            foreach ($datosC43->accounts as $cuentas){
                foreach ($cuentas->entries as $valor){
                    $bancos[] = $valor->banco;
                    } 
                }
            } else {

                $bancos = new Banco();
            }
            
        return $this->render('banco/new.html.twig', [
           'bancos' => $bancos,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/admin/importarTk", name="importarTk", methods={"GET","POST"})
     */
    public function importarTk(Request $request , DetallecestaRepository $detallecestaRepository)
    {
        $datos = new Importar_Ticket;
        $cestas = $datos->devolertickets();
        return $this->render('ticket.html.twig', [
            'cestas' => $cestas,
        ]);
    }
}
