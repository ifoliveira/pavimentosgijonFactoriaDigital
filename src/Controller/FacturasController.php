<?php

namespace App\Controller;

use App\Repository\DocumentoRepository;
use App\Entity\Documento;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Route('admin/facturas')]

final class FacturasController extends AbstractController
{
    #[Route('', name: 'admin_factura_index', methods: ['GET'])]
    public function index(
        Request $request,
        DocumentoRepository $documentoRepository
    ): Response {
        $anioActual = (int) (new \DateTimeImmutable())->format('Y');

        $anio = $request->query->getInt('anio', $anioActual);
        $trimestre = $request->query->getInt(
            'trimestre',
            $this->obtenerTrimestreActual()
        );

        if ($anio < 2000 || $anio > 2100) {
            $anio = $anioActual;
        }

        if (!in_array($trimestre, [0, 1, 2, 3, 4], true)) {
            $trimestre = $this->obtenerTrimestreActual();
        }

        [$fechaDesde, $fechaHasta] = $this->obtenerPeriodoTrimestre(
            $anio,
            $trimestre
        );

        $facturas = $documentoRepository->findFacturasPorPeriodo(
            $fechaDesde,
            $fechaHasta
        );

        $resumen = $documentoRepository->getResumenFacturasPorPeriodo(
            $fechaDesde,
            $fechaHasta
        );

        return $this->render('admin_pro/factura/index.html.twig', [
            'facturas' => $facturas,
            'resumen' => $resumen,
            'anio' => $anio,
            'trimestre' => $trimestre,
            'esAnual' => $trimestre === 0,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta->modify('-1 day'),
            'aniosDisponibles' => range($anioActual, $anioActual - 5),
        ]);
    }

    private function obtenerTrimestreActual(): int
    {
        $mes = (int) (new \DateTimeImmutable())->format('n');

        return (int) ceil($mes / 3);
    }

    private function obtenerPeriodoTrimestre(
        int $anio,
        int $trimestre
    ): array {
        if ($trimestre === 0) {
            $fechaDesde = new \DateTimeImmutable(sprintf('%04d-01-01', $anio));

            return [$fechaDesde, $fechaDesde->modify('+1 year')];
        }

        $mesInicial = (($trimestre - 1) * 3) + 1;

        $fechaDesde = new \DateTimeImmutable(sprintf(
            '%04d-%02d-01',
            $anio,
            $mesInicial
        ));

        $fechaHasta = $fechaDesde->modify('+3 months');

        return [$fechaDesde, $fechaHasta];
    }

    #[Route('/{id}/pdf',name: 'admin_factura_pdf',methods: ['GET'])]
    public function pdf(Documento $ticket): Response
    {
        if ($ticket->getTipoDocumento() !== 'factura') {
            throw $this->createNotFoundException(
                'El documento indicado no es un ticket.'
            );
        }

        $html = $this->renderView(
            'admin_pro/factura/pdf.html.twig',
            [
                'ticket' => $ticket,
            ]
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');

        /*
        * Para un ticket estrecho puedes usar un tamaño personalizado.
        * 80 mm de ancho son aproximadamente 226 puntos.
        */
        $dompdf->setPaper('A4', 'portrait');

        $dompdf->render();

        $nombreArchivo = sprintf(
            'ticket_%s.pdf',
            str_replace('-', '_', $ticket->getNumeroFormateado())
        );

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'inline; filename="%s"',
                    $nombreArchivo
                ),
            ]
        );
    }    

    #[Route('/pdf-trimestre/{anio}/{trimestre}',name: 'admin_factura_pdf_trimestre',methods: ['GET'])]
    public function pdfTrimestre(
        int $anio,
        int $trimestre,
        DocumentoRepository $documentoRepository
    ): Response {
        if (!in_array($trimestre, [0, 1, 2, 3, 4], true)) {
            throw $this->createNotFoundException(
                'Periodo no válido.'
            );
        }

        [$fechaDesde, $fechaHasta] = $this->obtenerPeriodoTrimestre(
            $anio,
            $trimestre
        );

        $tickets = $documentoRepository->findFacturasPorPeriodo(
            $fechaDesde,
            $fechaHasta
        );

        if (empty($tickets)) {
            throw $this->createNotFoundException(
                'No hay facturas en el periodo seleccionado.'
            );
        }

        $html = $this->renderView(
            'admin_pro/factura/pdf_trimestre.html.twig',
            [
                'tickets' => $tickets,
                'anio' => $anio,
                'trimestre' => $trimestre,
            ]
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html, 'UTF-8');

        /*
        * Para enviar al asesor recomiendo A4.
        * Un ticket por página.
        */
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombreArchivo = $trimestre === 0
            ? sprintf('Facturas_Anual_%d.pdf', $anio)
            : sprintf('Facturas_%dT_%d.pdf', $trimestre, $anio);

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',

                /*
                * attachment = descarga automática
                */
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    $nombreArchivo
                ),
            ]
        );
    }    



    #[Route('/resumen/{anio}/{trimestre}/pdf',name: 'admin_factura_resumen_pdf', methods: ['GET'])]
    public function resumenPdf(
        int $anio,
        int $trimestre,
        DocumentoRepository $documentoRepository,

    ): Response {
        [$fechaDesde, $fechaHasta] = $this->obtenerPeriodoTrimestre(
            $anio,
            $trimestre
        );

        $tickets = $documentoRepository->findFacturasPorPeriodo(
            $fechaDesde,
            $fechaHasta
        );

        $resumen = $documentoRepository->getResumenFacturasPorPeriodo(
            $fechaDesde,
            $fechaHasta
        );

        $html = $this->renderView(
            'admin_pro/factura/resumen_pdf.html.twig',
            [
                'tickets' => $tickets,
                'resumen' => $resumen,
                'anio' => $anio,
                'trimestre' => $trimestre,
                'esAnual' => $trimestre === 0,
                'fechaDesde' => $fechaDesde,
                'fechaHasta' => $fechaHasta->modify('-1 day'),
            ]
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombre = $trimestre === 0
            ? sprintf('Resumen_Facturas_Anual_%d.pdf', $anio)
            : sprintf('Resumen_Facturas_%dT_%d.pdf', $trimestre, $anio);

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    $nombre
                ),
            ]
        );
    }    


    #[Route('/resumen/{anio}/{trimestre}/csv',name: 'admin_factura_resumen_csv',methods: ['GET'])]
    public function resumenCsv(
        int $anio,
        int $trimestre,
        DocumentoRepository $documentoRepository
    ): StreamedResponse {
        [$fechaDesde, $fechaHasta] = $this->obtenerPeriodoTrimestre(
            $anio,
            $trimestre
        );

        $tickets = $documentoRepository->findFacturasPorPeriodo(
            $fechaDesde,
            $fechaHasta
        );

        $nombre = $trimestre === 0
            ? sprintf('Resumen_Facturas_Anual_%d.csv', $anio)
            : sprintf('Resumen_Facturas_%dT_%d.csv', $trimestre, $anio);

        $response = new StreamedResponse(
            function () use ($tickets): void {
                $salida = fopen('php://output', 'wb');

                if ($salida === false) {
                    throw new \RuntimeException(
                        'No se pudo abrir la salida CSV.'
                    );
                }

                /*
                * BOM UTF-8 para que Excel reconozca tildes y ñ.
                */
                fwrite($salida, "\xEF\xBB\xBF");

                // Fuerza a Excel a utilizar punto y coma como separador de
                // columnas, para que las comas de los importes no generen
                // columnas adicionales.
                fwrite($salida, "sep=;\r\n");

                fputcsv(
                    $salida,
                    [
                        'Número',
                        'Fecha',
                        'Base imponible',
                        'IVA',
                        'Total',
                        'Total cobrado',
                        'Estado de cobro',
                    ],
                    ';'
                );

                foreach ($tickets as $ticket) {


                    fputcsv(
                        $salida,
                        [
                            $ticket->getNumeroFormateado(),
                            $ticket->getFechaEmision()?->format('d/m/Y'),
                            number_format(
                                (float) $ticket->getBaseImponible(),
                                2,
                                ',',
                                ''
                            ),
                            number_format(
                                (float) $ticket->getTotalIva(),
                                2,
                                ',',
                                ''
                            ),
                            number_format(
                                (float) $ticket->getTotal(),
                                2,
                                ',',
                                ''
                            ),
                            number_format(
                                (float) $ticket->getTotalCobrado(),
                                2,
                                ',',
                                ''
                            ),
                            $ticket->getEstadoCobro(),
                        ],
                        ';'
                    );
                }

                fclose($salida);
            }
        );

        $response->headers->set(
            'Content-Type',
            'text/csv; charset=UTF-8'
        );

        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="%s"', $nombre)
        );

        return $response;
}    
}
