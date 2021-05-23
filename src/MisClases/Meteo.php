<?php
namespace App\MisClases;


class Meteo 
{

    var $_vars;
    var $_icons = array('801'=>'wi wi-cloudy', 
                        '802'=>'wi wi-cloudy',
                        '803'=>'wi wi-cloudy',
                        '804'=>'wi wi-cloudy',
                        '800'=>'wi wi-day-sunny',
                        '500'=>'wi wi-rain',
                        '501'=>'wi wi-rain',
                        '502'=>'wi wi-rain',
                        '503'=>'wi wi-rain',
                        '504'=>'wi wi-rain'
                    );


    function __construct() {
        /**  1 -> OBTENIENDO EL CONTENIDO */
        $json_file = file_get_contents('https://api.openweathermap.org/data/2.5/onecall?units=metric&lang=sp&lat=43.5357&lon=-5.6615&exclude=minutely,hourly&appid=696898090f2a7c7554d8c9cd040d2791');
        $this->_vars = json_decode($json_file);
    }
    
    function __temperatura() {
        $cond = $this->_vars->current;
        $temp_c = $cond->temp;

        return $temp_c;
    }

    function icon() {
        $cond = $this->_vars->current;
        $clave[0][] = $cond->temp;
        $clave[0][] = $this->_icons[$cond->weather[0]->id]; 
        $clave[0][] = $cond->weather[0]->description; 
        $futuro = $this->_vars->daily;
        $clave[1][] = $futuro[1]->temp->day;
        $clave[1][] = $this->_icons[$futuro[1]->weather[0]->id];
        $clave[1][] = $futuro[1]->weather[0]->description;  
        $clave[2][] = $futuro[2]->temp->day;
        $clave[2][] = $this->_icons[$futuro[2]->weather[0]->id];
        $clave[2][] = $futuro[2]->weather[0]->description;  
        $clave[3][] = $futuro[3]->temp->day;  
        $clave[3][] = $this->_icons[$futuro[3]->weather[0]->id];
        $clave[3][] = $futuro[3]->weather[0]->description;  
        return $clave;
    }
 


}
?>