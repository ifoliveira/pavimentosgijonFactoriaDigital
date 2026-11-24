<?php

namespace App\Controller;

use App\Repository\DocumentoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Documento;
use App\Service\Stock\StockReservaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;



#[Route('/admin/comercial')]
class ComercialPresupuestoController extends AbstractController
{
    #[Route('/presupuestos', name: 'admin_comercial_presupuestos', methods: ['GET'])]
    public function presupuestos(DocumentoRepository $documentoRepository): Response
    {
        $presupuestos = $documentoRepository->createQueryBuilder('d')
            ->leftJoin('d.proyecto', 'p')->addSelect('p')
            ->leftJoin('d.cliente', 'c')->addSelect('c')
            ->where('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial IN (:estadosPendientes)')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estadosPendientes', ['borrador', 'entregado'])
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $resumen = [
            'borrador' => 0,
            'entregado' => 0,
            'totalImporte' => 0,
        ];

        foreach ($presupuestos as $presupuesto) {
            $estado = $presupuesto->getEstadoComercial() ?? 'borrador';

            if (isset($resumen[$estado])) {
                $resumen[$estado]++;
            }

            $resumen['totalImporte'] += (float) $presupuesto->getTotal();
        }

        return $this->render('comercial/presupuestos.html.twig', [
            'presupuestos' => $presupuestos,
            'resumen' => $resumen,
        ]);
    }



    #[Route('/presupuestos/{id}/estado', name: 'admin_comercial_presupuesto_estado', methods: ['POST'])]
    public function cambiarEstado(
        Documento $presupuesto,
        Request $request,
        EntityManagerInterface $em,
        StockReservaService $stockReservaService
    ): RedirectResponse {
        if ($presupuesto->getTipoDocumento() !== 'presupuesto') {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('comercial_presupuesto_'.$presupuesto->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token de seguridad no válido.');
        }

        $estado = $request->request->getString('estado');
        $estadoActual = $presupuesto->getEstadoComercial();
        $transiciones = [
            'borrador' => ['seguimiento', 'entregado', 'rechazado'],
            'entregado' => ['seguimiento', 'rechazado'],
            'rechazado' => ['entregado'],
        ];

        if (!in_array($estado, $transiciones[$estadoActual] ?? [], true)) {
            $this->addFlash('error', 'Este presupuesto ya no admite esa acción comercial. Actualiza el listado.');
            return $this->redirectToRoute('admin_comercial_presupuestos');
        }

        $nota = trim($request->request->getString('nota_comercial'));
        $motivo = $request->request->getString('motivo_perdida');
        if ($estado === 'seguimiento' && $nota === '') {
            $this->addFlash('error', 'Indica la acción comercial realizada en la nota de seguimiento.');
            return $this->redirectToRoute('admin_comercial_presupuestos');
        }
        if ($estado === 'rechazado' && !in_array($motivo, ['precio_alto', 'sin_respuesta', 'lo_hace_otro', 'aplazado', 'otro'], true)) {
            $this->addFlash('error', 'Selecciona un motivo de rechazo válido.');
            return $this->redirectToRoute('admin_comercial_presupuestos');
        }

        $fecha = new \DateTimeImmutable();
        if ($estado !== 'seguimiento') {
            $presupuesto->setEstadoComercial($estado);
        }
        if ($estado === 'rechazado') {
            $presupuesto->setMotivoPerdida($motivo);
            $stockReservaService->liberarReservasDocumento($presupuesto);
        } elseif ($estadoActual === 'rechazado') {
            $presupuesto->setMotivoPerdida(null);
        }
        if ($nota !== '') {
            $entrada = $fecha->format('d/m/Y H:i').' — '.$nota;
            $presupuesto->setNotaComercial(trim(($presupuesto->getNotaComercial() ?? '')."\n".$entrada));
        }
        $presupuesto->setFechaUltimoSeguimiento($fecha);

        $em->flush();

        $this->addFlash('success', 'Acción comercial registrada.');

        return $this->redirectToRoute('admin_comercial_presupuestos');
    }    

    #[Route('/presupuestos/rechazados', name: 'admin_comercial_presupuestos_rechazados')]
    public function rechazados(DocumentoRepository $documentoRepository): Response
    {
        $presupuestos = $documentoRepository->createQueryBuilder('d')
            ->where('d.tipoDocumento = :tipo')
            ->andWhere('d.estadoComercial = :estado')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estado', 'rechazado')
            ->orderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('comercial/rechazados.html.twig', [
            'presupuestos' => $presupuestos,
        ]);
    }    
}