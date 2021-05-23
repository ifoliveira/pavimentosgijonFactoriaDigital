<?php
namespace App\MisClases;

use App\Entity\Cestas;

class Importar_Ticket 
{
    var $_vars;


    function __construct() {
        /**  1 -> OBTENIENDO EL CONTENIDO */
        $json_file = file_get_contents('../public/facturas.json');
        $this->_vars = json_decode($json_file);
    }

    function devolertickets() {
        
        $cond = $this->_vars[2]->data;
        return $cond;
    }
}
?>