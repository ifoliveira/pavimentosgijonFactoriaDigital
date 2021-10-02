<?php
namespace App\MisClases;
/* A wrapper to do organise item names & prices into columns */
   
class item
    {
        private $name;
        private $price;
        private $dollarSign;
        private $cantidad;
    
        public function __construct($cantidad = '' ,$name = '', $price = '', $dollarSign = false)
        {
            $this -> cantidad = strval($cantidad);
            $this -> name = $name;
            $this -> price = $price;
            $this -> dollarSign = $dollarSign;
        }
        
        public function __toString()
        {
            $rightCols = 10;
            $leftCols = 38;
            if ($this -> dollarSign) {
                $leftCols = $leftCols / 2 - $rightCols / 2;
            }
            $left = str_pad($this->cantidad . ' ' . $this -> name, $leftCols) ;
            
            $sign = ($this -> dollarSign ? ' €' : '');
            $right = str_pad( $this -> price . $sign , $rightCols, ' ', STR_PAD_LEFT);
            return "$left$right\n";
        }
    }
?>