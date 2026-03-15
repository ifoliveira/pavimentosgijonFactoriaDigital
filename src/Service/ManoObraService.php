<?php
namespace App\Service;

use App\Entity\ManoObra;
use App\Entity\Presupuestos;
use App\Entity\TipoManoObra;
use Doctrine\ORM\EntityManagerInterface;

class ManoObraService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Crea las líneas de mano de obra iniciales para un presupuesto.
     * No hace flush — el controlador es responsable.
     */
    public function iniciarPresupuesto(Presupuestos $presupuesto): void
    {
        $tiposManoObra = $this->em->getRepository(TipoManoObra::class)->findAll();

        foreach ($tiposManoObra as $tipo) {
            $manoObra = new ManoObra();
            $manoObra->setPresupuestoMo($presupuesto);
            $manoObra->setCategoriaMo($tipo);
            $manoObra->setTipoMo($tipo->getTipoTm());
            $this->em->persist($manoObra);
        }
    }
}