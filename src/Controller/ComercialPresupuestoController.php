<?php

namespace App\Controller;

use App\Repository\DocumentoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Documento;
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
            ->andWhere('d.estadoComercial <> :estadoConvertido')
            ->andWhere('d.estadoComercial <> :estadoRechazado')
            ->setParameter('tipo', 'presupuesto')
            ->setParameter('estadoConvertido', 'convertido')
            ->setParameter('estadoRechazado', 'rechazado')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $resumen = [
            'borrador' => 0,
            'entregado' => 0,
            'aceptado' => 0,
            'convertido' => 0,
            'rechazado' => 0,
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
        EntityManagerInterface $em
    ): RedirectResponse {
        if ($presupuesto->getTipoDocumento() !== 'presupuesto') {
            throw $this->createNotFoundException();
        }

        $estado = $request->request->get('estado');
        $motivo = $request->request->get('motivo_perdida');
        $notaComercial = $request->request->get('nota_comercial');

        $estadosPermitidos = [
            'borrador',
            'entregado',
            'aceptado',
            'rechazado',
        ];

        if (!in_array($estado, $estadosPermitidos, true)) {
            $this->addFlash('danger', 'Estado comercial no válido.');
            return $this->redirectToRoute('admin_comercial_presupuestos');
        }

        $presupuesto->setEstadoComercial($estado);

        if ($estado === 'rechazado' && method_exists($presupuesto, 'setMotivoPerdida')) {
            $presupuesto->setMotivoPerdida($motivo);
            $presupuesto->setNotaComercial($notaComercial);
        }

        if (method_exists($presupuesto, 'setFechaUltimoSeguimiento')) {
            $presupuesto->setFechaUltimoSeguimiento(new \DateTimeImmutable());
        }

        $em->flush();

        $this->addFlash('success', 'Estado del presupuesto actualizado.');

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