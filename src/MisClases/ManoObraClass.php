<?php
namespace App\MisClases;

use App\Entity\ManoObra;
use Doctrine\ORM\EntityManager;
use App\Entity\TipoManoObra;

/* A wrapper to do organise item names & prices into columns */
   
class ManoObraClass
    {
        protected $em;

        public function __construct(EntityManager $em)
        {
            $this->em = $em;
        }
        
        public function IniciarPresupuesto($presupuesto)
        {
            $tipoManoObra = $this->em->getRepository(TipoManoObra::class)->findAll();
            var_dump("ALTA DE PRESUPUESTO");
            foreach($tipoManoObra as $tipo) {
                var_dump($tipo->getTipotm());
                $this->alta($tipo, $presupuesto);

            }
        }

        private function alta( $tipo, $presupuesto)
        {
            $manoObra = new ManoObra;
            $manoObra->setPresupuestoMo($presupuesto);
            $manoObra->setCategoriaMo($tipo);
            $manoObra->setTipoMo($tipo->getTipoTm());
            $this->em->persist($manoObra);
            $this->em->flush();        
        }

    }
?>