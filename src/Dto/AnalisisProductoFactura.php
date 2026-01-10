<?php
namespace App\Dto;

use App\Entity\Productos;
use App\Entity\DetalleCesta;

class AnalisisProductoFactura
{
    public Productos $productoFactura;
    public DetalleCesta $detalle;
    public float $costeAnterior;
    public float $costeNuevo;
    public float $diferencia;

    public function __construct(Productos $productoFactura, DetalleCesta $detalle, float $costeNuevo)
    {
        $this->productoFactura = $productoFactura;
        $this->detalle = $detalle;
        $this->costeAnterior = $detalle->getPrecioDc();
        $this->costeNuevo = $costeNuevo;
        $this->diferencia = $costeNuevo - $this->costeAnterior;
    }
}
?>