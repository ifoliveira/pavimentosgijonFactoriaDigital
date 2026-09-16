<?php

namespace App\Tests\Service;

use App\Entity\Documento;
use App\Entity\DocumentoLinea;
use App\Integration\BudgetFlow\BudgetFlowClient;
use App\Repository\DocumentoLineaRepository;
use App\Repository\ProductosRepository;
use App\Repository\StockMovimientoRepository;
use App\Repository\StockReservaRepository;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use App\Service\Documento\DescripcionPresupuestoFormatter;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Documento\DocumentoLineaService;
use App\Service\PresupuestoDuchaBuilderService;
use App\Service\Proyecto\ProyectoCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PresupuestoDuchaBuilderServiceTest extends TestCase
{
    public function testRespetaTipoServicioDevueltoPorBudgetFlowAunqueLaDescripcionParezcaProducto(): void
    {
        $documento = new Documento();
        $configuracion = new \App\Entity\DocumentoConfiguracion();
        $configuracion->setDatos([
            'largo_plato' => 120,
            'ancho_plato' => 80,
        ]);

        $lineaService = $this->crearDocumentoLineaServiceMock();
        $lineaService
            ->expects(self::once())
            ->method('crearLineaDesdeConfigurador')
            ->with(
                self::identicalTo($documento),
                'Plato ducha con instalacion incluida',
                1.0,
                4070.0,
                0.0,
                'servicio',
                null,
                'configurador',
                10.0,
                false
            );

        $service = new PresupuestoDuchaBuilderService(
            $this->crearBudgetFlowConfiguratorService([
                'descripcion' => 'Plato ducha con instalacion incluida',
                'tipo' => 'servicio',
                'cantidad' => 1,
                'precioUnitarioSinIva' => 3700,
                'tipoIva' => 21,
            ]),
            $lineaService,
        );

        $service->generar($documento, $configuracion);
    }

    public function testSinTipoBudgetFlowConservaFallbackProductoAnteriorDelConfiguradorDucha(): void
    {
        $documento = new Documento();
        $configuracion = new \App\Entity\DocumentoConfiguracion();
        $configuracion->setDatos([
            'largo_plato' => 120,
            'ancho_plato' => 80,
        ]);

        $lineaService = $this->crearDocumentoLineaServiceMock();
        $lineaService
            ->expects(self::once())
            ->method('crearLineaDesdeConfigurador')
            ->with(
                self::identicalTo($documento),
                'Instalacion banera por plato',
                1.0,
                1210.0,
                0.0,
                'producto',
                null,
                'configurador',
                21.0,
                false
            );

        $service = new PresupuestoDuchaBuilderService(
            $this->crearBudgetFlowConfiguratorService([
                'descripcion' => 'Instalacion banera por plato',
                'cantidad' => 1,
                'precioUnitarioSinIva' => 1000,
                'tipoIva' => 21,
            ]),
            $lineaService,
        );

        $service->generar($documento, $configuracion);
    }

    private function crearDocumentoLineaServiceMock(): DocumentoLineaService
    {
        $lineaService = $this->getMockBuilder(DocumentoLineaService::class)
            ->setConstructorArgs([
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(ProductosRepository::class),
                $this->createMock(DocumentoLineaRepository::class),
                $this->createMock(ProyectoCalculatorService::class),
                $this->createMock(StockMovimientoRepository::class),
                $this->createMock(StockReservaRepository::class),
                new DescripcionPresupuestoFormatter(),
                new DocumentoCalculatorService(),
            ])
            ->onlyMethods([
                'eliminarLineasDocumentoPorOrigenes',
                'resolverTipoIvaParaNuevaLineaFactura',
                'crearLineaDesdeConfigurador',
                'recalcularDocumentoCompleto',
            ])
            ->getMock();

        $lineaService
            ->method('resolverTipoIvaParaNuevaLineaFactura')
            ->willReturn(10.0);

        return $lineaService;
    }

    private function crearBudgetFlowConfiguratorService(array $linea): BudgetFlowConfiguratorService
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                    'codigo' => 'conjunto_ducha',
                    'tipo' => 'compuesto',
                    'componentes' => [],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                    'valido' => true,
                    'errores' => [],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                    'validacion' => [
                        'valido' => true,
                        'errores' => [],
                    ],
                    'lineas' => [$linea],
                    'avisos' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        return new BudgetFlowConfiguratorService(
            new BudgetFlowClient($httpClient)
        );
    }
}
