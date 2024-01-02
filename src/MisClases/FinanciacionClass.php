<?php
namespace App\MisClases;
/* A wrapper to do organise item names & prices into columns */
   
class FinanciacionClass
    {
        private $importe;
        private $plazo;
        private $modalidad;
        private $financia3;
    
        public function __construct($importe ,$plazo)
        {
            $this -> importe = strval($importe);
            $this -> plazo = strval($plazo);
            $this -> modalidad = 2  ;
            $this -> financia3 = 0.03;
        }

        public function setmodalidad($modalidadnew): self
        {
            $this->modalidad = $modalidadnew;
    
            return $this;
        }        
        
        public function obtenermensualidad($modal)
        {   

            if ($modal != 0) {
                $this->setmodalidad($modal);
            }

            switch ($this->modalidad){
                case 2:
                    $mensualidad = $this->importe/$this->plazo;
                    break;
                case 3:
                    $mensualidad = ($this->importe + ($this->importe*$this->financia3))/$this->plazo;
                        break;       
                case 4:
                    if ($this->porcentajeinteres() == 9999) {
                        $mensualidad = 9999;
                    }else{
                        $mensualidad = $this->importe * $this->porcentajeinteres();
                    }
                        break;                                       
                default:
                    $mensualidad = 0;
            }


            return $mensualidad;
        }

        public function costecomercio($modal)
        {   

            if ($modal != 0) {
                $this->setmodalidad($modal);
            }

            switch ($this->modalidad){
                case 2:
                    $coste = $this->importe * ($this->porcentajeinteres()/100);
                    break;
                case 3:
                    $coste = $this->importe * ($this->porcentajeinteres()/100);
                    break;                    
                case 4:
                    $coste = 0;
                    break;                    
                default:                
                    $coste = 0;
            }


            return $coste;
        }



        public function porcentajeinteres()
        {

                switch ($this->modalidad) {
                    case 2:
                        switch (true) {
                            case $this->plazo < 7:
                                return  1.5;
                                break;
                            case $this->plazo < 11:
                                return  1.75;
                                break;
                            case $this->plazo < 13:
                                return  2;
                                break;
                            case $this->plazo < 19:                                
                                return  4;
                                break;
                            default:
                                return 9999;
                        }                                
                        break;
                    case 3:
                        switch (true) {
                            case $this->plazo < 13:
                                return  0;
                                break;
                            case $this->plazo < 19:
                                return  1;
                                break;
                            case $this->plazo < 25:
                                return  3;
                                break;
                            case $this->plazo < 37:                               
                                return  4.95;
                                break;
                                default:
                                return 9999;                                
                        }
                        break;
                    case 4:
                        switch ($this->plazo) {
                            case 3:
                                return  0.33776;
                            case 6:
                                return  0.17055;

                            case 9:
                                return  0.11482;

                            case 12:                                
                                return  0.086965;
                            case 18:
                                return  0.059117;

                            case 24:
                                return  0.045204;

                            case 36:
                                return  0.031313 ;                                   
                            case 48:                               
                                return  0.024389;
                            case 60:
                                return  0.020252;
                            default:
                                return 9999;                                  

                            break;
                        }
                        break;
                }
            return 0;
        }


    }
?>