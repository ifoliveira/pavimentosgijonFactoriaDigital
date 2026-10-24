<?php

namespace App\Tests\Controller;

use App\Controller\FacturasController;
use App\Entity\Documento;
use App\Repository\DocumentoRepository;
use PHPUnit\Framework\TestCase;

final class FacturasCsvTest extends TestCase
{
    public function testResumenAnualUsaElNombreDeArchivoAnual(): void
    {
        $repository = $this->createMock(DocumentoRepository::class);
        $repository->expects(self::once())
            ->method('findFacturasPorPeriodo')
            ->willReturnCallback(function (\DateTimeInterface $desde, \DateTimeInterface $hasta): array {
                self::assertSame('2026-01-01', $desde->format('Y-m-d'));
                self::assertSame('2027-01-01', $hasta->format('Y-m-d'));

                return [];
            });

        $response = (new FacturasController())->resumenCsv(2026, 0, $repository);

        self::assertSame(
            'attachment; filename="Resumen_Facturas_Anual_2026.csv"',
            $response->headers->get('Content-Disposition')
        );
    }

    public function testCsvDeclaraPuntoYComaYConservaLosImportesConComaDecimal(): void
    {
        $factura = (new Documento())
            ->setTipoDocumento('factura')
            ->setSerie('F2026')
            ->setNumero(1)
            ->setBaseImponible('100.50')
            ->setTotalIva('21.11')
            ->setTotal('121.61')
            ->setTotalCobrado('121.61')
            ->setEstadoCobro('cobrado');
        $repository = $this->createMock(DocumentoRepository::class);
        $repository->method('findFacturasPorPeriodo')->willReturn([$factura]);

        $response = (new FacturasController())->resumenCsv(2026, 4, $repository);
        ob_start();
        try {
            $response->sendContent();
            $csv = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringStartsWith("\xEF\xBB\xBFsep=;\r\n", $csv);
        $rows = array_map(
            static fn (string $line): array => str_getcsv($line, ';'),
            explode("\n", trim(substr($csv, strlen("\xEF\xBB\xBFsep=;\r\n"))))
        );

        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertCount(7, $row);
        }
        self::assertSame('Estado de cobro', $rows[0][6]);
        self::assertSame(['100,50', '21,11', '121,61', '121,61', 'cobrado'], array_slice($rows[1], 2));
    }
}
