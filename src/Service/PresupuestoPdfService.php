<?php

namespace App\Service;

use App\Entity\PresupuestosLead;
use App\Service\PresupuestoCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PresupuestoPdfService
{
    private PresupuestoCalculatorService $calculator;
    private EntityManagerInterface $em;
    private Environment $twig;

    public function __construct(PresupuestoCalculatorService $calculator, EntityManagerInterface $em, Environment $twig)
    {
        $this->calculator = $calculator;
        $this->em = $em;
        $this->twig = $twig;
    }

    public function generarPdfResponse(PresupuestosLead $lead): Response
    {
        $json = $lead->getJsonPresupuesto();
        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        $res = $this->calculator->calcular($lead->getTipoReforma(), $json);

        $html = $this->twig->render('iapresupuesto/presupuesto.html.twig', [
            'nombre' => $lead->getNombre(),
            'email' => $lead->getEmail(),
            'tipo' => $lead->getTipoReforma(),
            'total' => $res['total'],
            'mano_obra' => $res['mano_obra'],
            'materiales' => $res['materiales'],
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $lead->setPdfDescargas(($lead->getPdfDescargas() ?? 0) + 1);
        $lead->setUltimoEvento(new \DateTime());
        $this->em->flush();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="presupuesto-bano-gijon.pdf"',
        ]);
    }
}
