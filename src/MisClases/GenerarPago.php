<?php

namespace App\MisClases;

use Doctrine\ORM\EntityManager;
use App\Entity\Pagos;
use App\Entity\Efectivo;
use App\Repository\BancoRepository;
use App\Repository\TiposmovimientoRepository;



class GenerarPago {

    protected $em;

    public function __construct( EntityManager $em )
    {
        $this->em = $em;
    }


    public function ticketPagoFinal($cesta, $importe, $tipopago, TiposmovimientoRepository $tiposmovimientoRepository) : Pagos
    {
        $tipomovimiento = $tiposmovimientoRepository->findOneBy(array('descripcionTm' => 'Ventas'));
        $efectivo = null;

        if ($tipopago == "Efectivo") {

            $efectivo = New Efectivo;
            $efectivo->setTipoEf($tipomovimiento);
            $efectivo->setConceptoEf("Ventas en efectivo");
            $efectivo->setFechaEf(new \DateTime());
            $efectivo->setImporteEf($importe);
            $this->em->persist($efectivo);  
            $this->em->flush();     
        }

        $pago = $this->alta($cesta, $importe,  $tipopago, $efectivo);
        return $pago;
    }

    private function alta($cestas, $importe, $tipopago, $efectivo) : Pagos
    {
        // Creamos un pago con lo que llegue en la señal

        $pago = New Pagos;

        $pago->setCesta($cestas);
        $pago->setFechaPg(new \DateTime());
        $pago->setImportePg($importe);
        $pago->setTipoPg($tipopago);
        $pago->setEfectivoPg($efectivo);
        $this->em->persist($pago);
        $this->em->flush();      
        
        return $pago;
    }

    public function conciliar($pago, $bancoId, BancoRepository $bancosRepository) : Pagos
    {
        $banco = $bancosRepository->findOneBy(array('id' => $bancoId));
 

        if ($pago->getImportePg() != $banco->getImporteBn()){

            $banconuevo = clone $banco;  
            $banconuevo->setImporteBn($pago->getImportePg());
            $banconuevo->setConciliado(true); 
            $importenuevo= $banco->getImporteBn() - $pago->getImportePg();
            $banco->setImporteBn($importenuevo);
            $pago->setBancoPg($banconuevo);
            $this->em->persist($banconuevo);

        } else {
            $pago->setBancoPg($banco);
            $banco->setConciliado(true);    
        }



        $this->em->persist($pago);
        $this->em->persist($banco);
       
        $this->em->flush();      

        return $pago;
    }

}

?>