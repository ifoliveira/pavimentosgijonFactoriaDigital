<?php

namespace App\Enum;

enum TipoPresupuestoWebComunicacion: string
{
    case PRESUPUESTO = 'presupuesto';
    case SEGUIMIENTO = 'seguimiento';
    case CIERRE = 'cierre';
}
