<?php

namespace App\Tests\Controller;

use App\Controller\TicketController;
use App\Entity\Documento;
use App\Repository\DocumentoRepository;
use PHPUnit\Framework\TestCase;

final class TicketCsvTest extends TestCase
{
    public function testResumenAnualUsaElPeriodoCompletoYNombreAnual(): void
    {
        $repository = $this->createMock(DocumentoRepository::class);
        $repository->expects(self::once())
            ->method('findTicketsPorPeriodo')
            ->willReturnCallback(function (\DateTimeInterface $desde, \DateTimeInterface $hasta): array {
                self::assertSame('2026-01-01', $desde->format('Y-m-d'));
                self::assertSame('2027-01-01', $hasta->format('Y-m-d'));

                return [];
            });

        $response = (new TicketController())->resumenCsv(2026, 0, $repository);

        self::assertSame(
            'attachment; filename="Resumen_Tickets_Anual_2026.csv"',
            $response->headers->get('Content-Disposition')
        );
    }

    public function testCsvConservaColumnasDecimalesYEstadosDeRectificacion(): void
    {
        $original = (new Documento())->setTipoDocumento('ticket')->setSerie('T2026')->setNumero(1)
            ->setBaseImponible('100.50')->setTotalIva('21.11')->setTotal('121.61')->setTotalCobrado('121.61');
        $rectificativa = (new Documento())->setTipoDocumento('ticket_rectificativo')->setSerie('RT2026')->setNumero(1)
            ->setBaseImponible('-100.50')->setTotalIva('-21.11')->setTotal('-121.61')->setTotalCobrado('-121.61')
            ->setDocumentoRectificado($original);
        $original->getRectificativas()->add($rectificativa);
        $normal = (new Documento())->setTipoDocumento('ticket')->setEstadoCobro('cobrado');
        $repository = $this->createMock(DocumentoRepository::class);
        $repository->method('findTicketsPorPeriodo')->willReturn([$original, $rectificativa, $normal]);
        $response = (new TicketController())->resumenCsv(2026, 4, $repository);
        ob_start();
        try {
            $response->sendContent();
            $csv = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $rows = array_map(static fn ($line) => str_getcsv($line, ','), explode("\n", trim(substr($csv, 3))));
        self::assertCount(4, $rows);
        foreach ($rows as $row) {
            self::assertCount(7, $row);
        }
        self::assertSame('Estado', $rows[0][6]);
        self::assertSame(['100.50', '21.11', '121.61', '121.61', 'Rectificada'], array_slice($rows[1], 2));
        self::assertSame(['-100.50', '-21.11', '-121.61', '-121.61', 'Rectifica'], array_slice($rows[2], 2));
        self::assertSame('cobrado', $rows[3][6]);
    }
}
