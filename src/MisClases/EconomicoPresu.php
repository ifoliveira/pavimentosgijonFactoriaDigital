<?php

namespace App\MisClases;

use Doctrine\ORM\EntityManager;
use App\Entity\Economicpresu;
use PhpParser\Node\Expr\Cast\Double;

class EconomicoPresu {

    protected $em;

    public function __construct( EntityManager $em )
    {
        $this->em = $em;
    }


    public function iniciarPresu($importemanoobra, $importependiente, $presupuesto)
    {

        $this->alta('Mano de Obra', $importemanoobra, 'H', $presupuesto, 'M');
        $this->alta('Materiales pendiente', $importependiente, 'H', $presupuesto, 'T');
        $this->alta('Pago Albañileria', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Fontanería', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Pintura', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Escayola', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Electricidad', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Desescombro', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Carpintero', 0, 'D', $presupuesto,'E');
        $this->alta('Pago Colocación', 0, 'D', $presupuesto,'E');
       
    }

    public function actualizaResto($importependiente, $presupuesto)
    {
        $economicpresu = new Economicpresu();
        $economicpresu = $this->em->getRepository(Economicpresu::class)->findOneBy(array('idpresuEco' => $presupuesto,'aplicaEco'=> 'T'));
        $economicpresu->setimporteEco($importependiente);
        $this->em->persist($economicpresu);
        $this->em->flush();             
    }

    private function alta($concepto, $importe, $debehaber, $presupuesto, $aplica)
    {
        $economicpresu = new Economicpresu();
        $economicpresu->setConceptoEco($concepto);
        $economicpresu->setimporteEco($importe);
        $economicpresu->setdebehaberEco($debehaber);
        $economicpresu->setaplicaEco($aplica);
        $economicpresu->setestadoEco('1');
        $economicpresu->setIdpresuEco($presupuesto);
        $this->em->persist($economicpresu);
        $this->em->flush();        
    }


    private function obtener_restante($presupuesto) : Double
    {
        $restante = 0;
        $economicpresu = new Economicpresu();
        $economicpresu = $this->em->getRepository(Economicpresu::class)->findAll(array('idpresuEco' => $presupuesto,'estadoEco'=> '1', 'debehaberEco' => 'H'));
        foreach ($economicpresu as &$valor) {
            $restante += $valor->getImporteEco();
        }


        return $restante;
    }    

}

?>