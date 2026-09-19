<?php

namespace App\Enum;

enum TipoPresupuestoWebInteraccion: string
{
    case DUDA = 'duda';
    case CAMBIO = 'cambio';
    case SOLICITA_CONTACTO = 'solicita_contacto';
    case SOLICITA_MEDICION = 'solicita_medicion';
    case NO_CONTINUA = 'no_continua';
}
