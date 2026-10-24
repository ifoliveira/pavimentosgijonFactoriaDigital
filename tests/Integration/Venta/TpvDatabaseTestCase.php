<?php

namespace App\Tests\Integration\Venta;

use App\Entity\Cestas;
use App\Entity\Documento;
use App\Entity\Productos;
use App\Entity\Tiposmovimiento;
use App\Service\Documento\DocumentoCalculatorService;
use App\Service\Venta\VentaDocumentoService;
use App\Service\Venta\VentaLineaInput;
use App\Service\Venta\VentaTpvInput;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base aislada: solo se ejecuta con TPV_DATABASE_TESTS=1.
 */
abstract class TpvDatabaseTestCase extends KernelTestCase
{
    protected ?Connection $adminConnection = null;
    protected ?EntityManagerInterface $em = null;
    protected ?string $databaseName = null;
    protected ManagerRegistry $registry;
    protected int $productoId;

    protected function setUp(): void
    {
        if (getenv('TPV_DATABASE_TESTS') !== '1') {
            self::markTestSkipped('Requiere TPV_DATABASE_TESTS=1 y MySQL local con permiso para bases temporales.');
        }

        self::bootKernel();
        $original = self::getContainer()->get('doctrine')->getManager();
        $params = $original->getConnection()->getParams();
        unset($params['dbname'], $params['url']);
        $this->adminConnection = DriverManager::getConnection($params);
        $this->databaseName = 'codex_tpv_test_' . bin2hex(random_bytes(6));
        $this->adminConnection->executeStatement('CREATE DATABASE ' . $this->databaseName);

        $params['dbname'] = $this->databaseName;
        $config = clone $original->getConfiguration();
        $this->registry = $this->createMock(ManagerRegistry::class);
        $factory = new class($this->registry) implements RepositoryFactory {
            public function __construct(private ManagerRegistry $registry) {}
            public function getRepository(EntityManagerInterface $entityManager, $entityName): \Doctrine\Persistence\ObjectRepository
            {
                $class = $entityManager->getClassMetadata($entityName)->customRepositoryClassName;
                return new $class($this->registry);
            }
        };
        $config->setRepositoryFactory($factory);
        $this->em = EntityManager::create($params, $config);
        $this->em->getEventManager()->addEventListener(
            [\Doctrine\ORM\Events::onFlush], new \App\EventSubscriber\TicketTpvInmutableListener()
        );
        $this->registry->method('getManager')->willReturnCallback(fn () => $this->em);
        $this->registry->method('getManagerForClass')->willReturnCallback(fn () => $this->em);
        $this->registry->method('resetManager')->willReturnCallback(function () use ($params, $config) {
            $this->em?->getConnection()->close();
            $this->em = EntityManager::create($params, $config);
            $this->em->getEventManager()->addEventListener(
                [\Doctrine\ORM\Events::onFlush], new \App\EventSubscriber\TicketTpvInmutableListener()
            );
            return $this->em;
        });
        $classes = [
            Documento::class, \App\Entity\DocumentoLinea::class, \App\Entity\DocumentoCobro::class,
            Cestas::class, \App\Entity\Detallecesta::class, \App\Entity\Pagos::class,
            \App\Entity\SerieDocumento::class, Productos::class, \App\Entity\Efectivo::class,
            \App\Entity\Tiposmovimiento::class,
            \App\Entity\Forecast::class, \App\Entity\Banco::class,
        ];
        $metadata = array_map(fn ($class) => $this->em->getClassMetadata($class), $classes);
        $schema = (new SchemaTool($this->em))->getSchemaFromMetadata($metadata);
        // Las asociaciones ajenas a la venta no se ejercitan en esta base aislada.
        foreach ($schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                if ($table->getName() !== 'documento' || $foreignKey->getLocalColumns() !== ['documento_rectificado_id']) {
                    $table->removeForeignKey($foreignKey->getName());
                }
            }
        }
        foreach ($schema->toSql($this->em->getConnection()->getDatabasePlatform()) as $sql) {
            $this->em->getConnection()->executeStatement($sql);
        }

        $producto = new Productos();
        $producto->setDescripcionPd('Producto TPV test')->setPrecioPd(6)->setPvpPd(10)->setStockPd(5);
        $tipoVentas = new Tiposmovimiento();
        $tipoVentas->setDescripcionTm('Ventas');
        $this->em->persist($producto);
        $this->em->persist($tipoVentas);
        $this->em->flush();
        $this->productoId = $producto->getId();
    }

    protected function tearDown(): void
    {
        $this->em?->getConnection()->close();
        if ($this->adminConnection !== null && $this->databaseName !== null) {
            $this->adminConnection->executeStatement('DROP DATABASE ' . $this->databaseName);
            $this->adminConnection->close();
        }
        parent::tearDown();
    }

}
