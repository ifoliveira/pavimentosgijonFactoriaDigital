<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Form\FinanciacionType;
use App\MisClases\FinanciacionClass;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class FinanciacionController extends AbstractController
{
    /**
     * @Route("/admin/financiacion", name="app_financiacion")
     */
    public function index(Request $request): Response
    {

        $form = $this->createForm(FinanciacionType::class);

        $form->handleRequest($request);
        $totalabonar = array (2 => 0, 3=>0, 4=>0);
        $mensualidad = array (2 => 0, 3=>0, 4=>0);
        $coste = array (2 => 0, 3=>0, 4=>0);
        $meses=0;
        $tipo = array (2 => 'Gratuito GA', 3=> 'Gratuito 3% Financiado', 4=>'Interes Cliente');
   

        if ($form->isSubmitted() && $form->isValid()) {
             $data = $form->getData();
             $financiacion = new FinanciacionClass($data['Importe'], $data['Plazo']);
             $mensualidad = array (2 => $financiacion->obtenermensualidad(2), 3=>$financiacion->obtenermensualidad(3), 4=>$financiacion->obtenermensualidad(4));
             $totalabonar = array (2 => $financiacion->obtenermensualidad(2)*$data['Plazo'], 3=>$financiacion->obtenermensualidad(3)*$data['Plazo'], 4=>$financiacion->obtenermensualidad(4)*$data['Plazo']);
             $meses = $data['Plazo'];
             $coste = array (2 => $financiacion->costecomercio(2), 3=>$financiacion->costecomercio(3), 4=>$financiacion->costecomercio(4));

        }

        return $this->render('financiacion/index.html.twig', [
            'controller_name' => 'FinanciacionController',
            'mensualidad'=>$mensualidad,
            'coste'=>$coste,
            'tipo'=> $tipo,
            'totalabonar'=>$totalabonar,
            'meses'=>$meses,
            'form' => $form->createView(),
        ]);
    }
}
