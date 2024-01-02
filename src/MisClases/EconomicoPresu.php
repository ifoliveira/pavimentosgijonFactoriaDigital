<?php

namespace App\MisClases;

use Doctrine\ORM\EntityManager;
use App\Entity\Economicpresu;
use App\Entity\TipoManoObra;
use DateTime;
use PhpParser\Node\Expr\Cast\Double;

class EconomicoPresu {

    protected $em;

    public function __construct( EntityManager $em )
    {
        $this->em = $em;
    }


    public function iniciarPresu($importemanoobra, $presupuesto)
    {
        $tipomano = $this->em->getRepository(TipoManoObra::class)->findAll();
        foreach ($presupuesto->getManoObra() as $tipo)
        {
            if ($tipo->getCoste() != 0 || $tipo->getTipoMo() == 'Otros')
            {
                $this->alta($tipo->getTipoMo(), $tipo->getCoste(), 'D', $presupuesto,'E');
            }
        }

        $this->alta('Mano de Obra', $importemanoobra, 'H', $presupuesto, 'M');
       
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
        $economicpresu->setTimestamp(New \DateTime());
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