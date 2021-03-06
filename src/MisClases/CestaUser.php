<?php

namespace App\MisClases;

use Doctrine\ORM\EntityManager;
use App\Entity\Cestas;
use App\Entity\Detallecesta;

class CestaUser {

    protected $em;

    public function __construct( EntityManager $em )
    {
        $this->em = $em;
    }


    public function getCestaUser($token)
    {
        $item = $this->em->getRepository(Cestas::class)->findOneBy(array('userCs' => $token,'estadoCs'=> '1'));
        return $item ? $item : null;
    }


    public function getCesta($cestaId )
    {
        $item = $this->em->getRepository(Cestas::class)->findOneBy(array('id' => $cestaId));
        return $item ? $item : null;
    }


    public function getCestaArray($cestaId)
    {
        $query = $this->em->createQuery('SELECT a FROM App\Entity\Detallecesta a WHERE a.cestaDc = ?1');
        $query->setparameter(1, $cestaId);
        $articles = $query->getResult(); // array of CmsArticle objects
        $i=0;
        $respuesta = array();
        foreach ($articles as $valor) {
          
            $respuesta1 = array("cantidad"    => $valor->getCantidadDc(),
                               "descripcion" => $valor->getProductoDc()->getDescripcionPd(),
                               "precio"      => $valor->getPvpDc());
            $respuesta[] = $respuesta1;
            $i++;
        }

        return $respuesta ? $respuesta : null;
    }

    public function getVolumen( $cestaId )
    {
        $item = $this->em->getRepository(Detallecesta::class)->findAll(array('cestaDc' => $this->getCesta($cestaId)));
        return $item ? count($item) : null;
    }

    public function getImporteTot( $cestaId )
    {
        $item = $this->em->getRepository(Detallecesta::class)->imptotalCesta($this->getCesta($cestaId));
        return $item ? $item : null;
    }

    public function getCantidadTot( $cestaId )
    {
        $item = $this->em->getRepository(Detallecesta::class)->cantotalCesta($this->getCesta($cestaId));
        return $item ? $item : 0;
    }

    public function getPrecioTot( $cestaId )
    {
        $item = $this->em->getRepository(Detallecesta::class)->preciototalCesta($this->getCesta($cestaId));
        return $item ? $item : 0;
    }

}

?>