<?php

namespace App\Tests\Integration\Venta;

use App\Entity\Documento;
use Twig\Environment;

final class TicketResumenTest extends TpvDatabaseTestCase
{
    public function testResumenRestaRectificativasDelPeriodoYMuestraSuOriginal(): void
    {
        $original = $this->documento('ticket', '2026-09-30', '100.00', '21.00', '121.00', 1);
        $venta = $this->documento('ticket', '2026-10-10', '200.00', '42.00', '242.00', 2);
        $rectificativa = $this->documento('ticket_rectificativo', '2026-10-20', '-100.00', '-21.00', '-121.00', 1);
        $rectificativa->setDocumentoRectificado($original);
        $this->documento('factura', '2026-10-20', '500.00', '105.00', '605.00', 1);
        $this->documento('ticket', '2027-01-01', '300.00', '63.00', '363.00', 3);
        $this->em->flush();

        $desde = new \DateTimeImmutable('2026-10-01');
        $hasta = new \DateTimeImmutable('2027-01-01');
        $repository = $this->em->getRepository(Documento::class);
        self::assertSame([
            ['trimestre' => 'T3', 'baseImponible' => 100.0, 'ivaCobrado' => 21.0, 'total' => 121.0],
            ['trimestre' => 'T4', 'baseImponible' => 100.0, 'ivaCobrado' => 21.0, 'total' => 121.0],
        ], $repository->getResumenTicketsTrimestral(new \DateTimeImmutable('2026-01-01'), $hasta));
        $resumen = $repository->getResumenTicketsPorPeriodo($desde, $hasta);
        self::assertSame(2, (int) $resumen['numeroTickets']);
        foreach (['totalBase' => 100, 'totalIva' => 21, 'totalVentas' => 121, 'totalCobrado' => 121] as $campo => $importe) {
            self::assertEquals($importe, $resumen[$campo]);
        }
        $tickets = $repository->findTicketsPorPeriodo($desde, $hasta);
        self::assertCount(2, $tickets);
        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->render('admin_pro/ticket/resumen_pdf.html.twig', [
            'tickets' => $tickets, 'resumen' => $resumen, 'anio' => 2026, 'trimestre' => 4,
            'fechaDesde' => $desde, 'fechaHasta' => $hasta->modify('-1 day'),
        ]);
        self::assertStringContainsString('Rectifica: T2026-0001', $html);
        self::assertStringContainsString('-121,00', $html);
        foreach (['pdf', 'pdf_trimestre'] as $plantilla) {
            $html = $twig->render('admin_pro/ticket/' . $plantilla . '.html.twig', [
                'ticket' => $rectificativa, 'tickets' => [$rectificativa],
            ]);
            self::assertStringContainsString('FACTURA SIMPLIFICADA RECTIFICATIVA', $html);
            self::assertStringContainsString('T2026-0001', $html);
            self::assertStringContainsString('30/09/2026', $html);
        }
    }

    public function testTrimestreConSoloRectificativasTieneImportesNegativos(): void
    {
        $original = $this->documento('ticket', '2025-12-31', '100.00', '21.00', '121.00', 1);
        $this->documento('ticket_rectificativo', '2026-01-01', '-100.00', '-21.00', '-121.00', 1)
            ->setDocumentoRectificado($original);
        $this->documento('ticket_rectificativo', '2027-01-01', '-200.00', '-42.00', '-242.00', 2);
        $this->em->flush();

        self::assertSame([
            ['trimestre' => 'T1', 'baseImponible' => -100.0, 'ivaCobrado' => -21.0, 'total' => -121.0],
        ], $this->em->getRepository(Documento::class)->getResumenTicketsTrimestral(
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2027-01-01')
        ));
    }

    private function documento(string $tipo, string $fecha, string $base, string $iva, string $total, int $numero): Documento
    {
        $doc = (new Documento())->setTipoDocumento($tipo)
            ->setSerie($tipo === 'ticket_rectificativo' ? 'RT2026' : ($tipo === 'factura' ? 'F2026' : 'T2026'))
            ->setNumero($numero)->setFechaEmision(new \DateTime($fecha))
            ->setBaseImponible($base)->setTotalIva($iva)->setTotal($total)->setTotalCobrado($total);
        $this->em->persist($doc);
        return $doc;
    }
}
