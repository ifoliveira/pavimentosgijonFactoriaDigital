<?php

namespace App\Enum;

enum EstadoPresupuestoWebComunicacion: string
{
    case PENDIENTE = 'pendiente';
    case ENVIADA = 'enviada';
    case ERROR = 'error';
    case CANCELADA = 'cancelada';
}
