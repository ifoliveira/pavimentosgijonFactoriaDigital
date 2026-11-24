<?php

namespace App\Tests\Service;

use App\Entity\Forecast;
use App\Repository\ForecastRepository;
use App\Service\ForecastService;
use PHPUnit\Framework\TestCase;

final class ForecastServiceAnioTest extends TestCase
{
    public function testFiltraElListadoYLaGraficaPorEjercicio(): void
    {
        $repository = $this->createMock(ForecastRepository::class);
        $repository->method('findBy')->willReturn([
            $this->forecast(-300, '2026-12-20'),
            $this->forecast(-500, '2027-02-10'),
            $this->forecast(800, '2027-03-15'),
            $this->forecast(-900, '2028-01-05'),
        ]);
        $service = new ForecastService($repository);

        $resultado = $service->getForecastPendiente(
            2000,
            new \DateTimeImmutable('2027-01-01'),
            new \DateTimeImmutable('2027-12-31'),
            new \DateTimeImmutable('2027-01-01'),
        );

        self::assertCount(2, $resultado['list']);
        self::assertSame('2027-02-10', $resultado['list'][0]->getFechaFr()->format('Y-m-d'));
        self::assertSame([
            ['x' => '2027-01-01', 'y' => 1700.0],
            ['x' => '2027-02-10', 'y' => 1200.0],
            ['x' => '2027-03-15', 'y' => 2000.0],
        ], $resultado['chartData']);
    }

    private function forecast(float $importe, string $fecha): Forecast
    {
        return (new Forecast())
            ->setEstadoFr('P')
            ->setImporteFr($importe)
            ->setFechaFr(new \DateTime($fecha));
    }
}
