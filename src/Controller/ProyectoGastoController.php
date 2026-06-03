<?php

namespace App\Controller;

use App\Entity\ProyectoGasto;
use App\Service\ProyectoGasto\ProyectoGastoService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route('/admin/proyecto-gasto')]
class ProyectoGastoController extends AbstractController

{
    #[Route('/{id}/confirmar', name: 'app_proyecto_gasto_confirmar', methods: ['POST'])]
    public function confirmar(
        ProyectoGasto $gasto,
        ProyectoGastoService $proyectoGastoService
    ): RedirectResponse {
        $proyectoId = $gasto->getProyecto()->getId();

        $proyectoGastoService->confirmar($gasto);

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyectoId,
        ]);
    }

    #[Route('/{id}/pagado', name: 'app_proyecto_gasto_pagado', methods: ['POST'])]
    public function pagado(
        ProyectoGasto $gasto,
        ProyectoGastoService $proyectoGastoService
    ): RedirectResponse {
        $proyectoId = $gasto->getProyecto()->getId();

        $proyectoGastoService->marcarPagado($gasto);

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyectoId,
        ]);
    }


    #[Route('/{id}/cancelar', name: 'app_proyecto_gasto_cancelar', methods: ['POST'])]
    public function cancelar(
        ProyectoGasto $gasto,
        ProyectoGastoService $proyectoGastoService
    ): RedirectResponse {
        $proyectoId = $gasto->getProyecto()->getId();

        $proyectoGastoService->cancelar($gasto);

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyectoId,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_proyecto_gasto_delete', methods: ['POST'])]
    public function delete(
        ProyectoGasto $gasto,
        ProyectoGastoService $proyectoGastoService
    ): RedirectResponse {
        $proyectoId = $gasto->getProyecto()->getId();

        $proyectoGastoService->eliminar($gasto);

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyectoId,
        ]);
    }    

    #[Route('/{id}/reactivar', name: 'app_proyecto_gasto_reactivar', methods: ['POST'])]
    public function reactivar(
        ProyectoGasto $gasto,
        ProyectoGastoService $proyectoGastoService
    ): RedirectResponse {
        $proyectoId = $gasto->getProyecto()->getId();

        $proyectoGastoService->reactivar($gasto);

        return $this->redirectToRoute('app_proyecto_show', [
            'id' => $proyectoId,
        ]);
    }    

}