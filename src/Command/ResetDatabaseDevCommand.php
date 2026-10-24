<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:dev:reset-database',
    description: 'Reinicia los datos de desarrollo conservando las tablas maestras.'
)]
class ResetDatabaseDevCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        /*
         * Protección fundamental.
         *
         * Este comando JAMÁS debe ejecutarse fuera de DEV.
         */
        if ($this->kernel->getEnvironment() !== 'dev') {
            $output->writeln('');
            $output->writeln(
                '<error>Este comando solamente puede ejecutarse en entorno DEV.</error>'
            );
            $output->writeln('');

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<fg=yellow;options=bold>ATENCIÓN</>');
        $output->writeln('');
        $output->writeln('Se eliminarán TODOS los datos de la base de datos');
        $output->writeln('excepto los contenidos en estas tablas:');
        $output->writeln('');
        $output->writeln('  - admin');
        $output->writeln('  - catalogo_producto');
        $output->writeln('  - catalogo_producto_configuracion');
        $output->writeln('  - tiposmovimiento');
        $output->writeln('  - serie_documento');
        $output->writeln('  - doctrine_migration_versions (tabla técnica)');
        $output->writeln('');

        $helper = $this->getHelper('question');

        $question = new ConfirmationQuestion(
            '¿Quieres continuar? [y/N] ',
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('');
            $output->writeln('<comment>Operación cancelada.</comment>');

            return Command::SUCCESS;
        }

        $connection = $this->entityManager->getConnection();

        /*
         * Estas tablas NO se borrarán.
         */
        $tablasConservar = [
            'admin',
            'catalogo_producto',
            'catalogo_producto_configuracion',
            'presupuesto_configurador',
            'presupuesto_configurador_campo',
            'productos',
            'serie_documento',
            'texto_mano_obra',
            'tipo_mano_obra',
            'tipoproducto', 
            'tiposmovimiento',  
            /*
             * No es dato de negocio, pero debe mantenerse.
             *
             * Si la borramos, Doctrine perdería el histórico
             * de migraciones ejecutadas.
             */
            'doctrine_migration_versions',
        ];

        try {
            /*
             * Obtenemos TODAS las tablas actuales.
             *
             * Esto tiene la ventaja de que si mañana añades una tabla
             * nueva al ERP, automáticamente será limpiada sin necesidad
             * de modificar este comando.
             */
            $schemaManager = $connection->createSchemaManager();

            $tablas = $schemaManager->listTableNames();

            $output->writeln('');
            $output->writeln('<info>Iniciando limpieza...</info>');
            $output->writeln('');

            /*
             * Desactivamos temporalmente las FK.
             *
             * Así no necesitamos preocuparnos del orden en el que
             * eliminamos proyecto, documentos, asignaciones, gastos,
             * facturas, etc.
             */
            $connection->executeStatement(
                'SET FOREIGN_KEY_CHECKS = 0'
            );

            foreach ($tablas as $tabla) {

                /*
                 * Las tablas maestras se conservan.
                 */
                if (in_array($tabla, $tablasConservar, true)) {
                    $output->writeln(
                        sprintf(
                            '<fg=cyan>CONSERVAR</>  %s',
                            $tabla
                        )
                    );

                    continue;
                }

                $output->writeln(
                    sprintf(
                        '<fg=red>VACIAR</>     %s',
                        $tabla
                    )
                );

                /*
                 * TRUNCATE:
                 *
                 * - elimina todos los registros
                 * - reinicia AUTO_INCREMENT
                 *
                 * Como hemos deshabilitado las FK podemos hacerlo
                 * independientemente del orden de las tablas.
                 */
                $connection->executeStatement(
                    sprintf(
                        'TRUNCATE TABLE `%s`',
                        str_replace('`', '``', $tabla)
                    )
                );
            }

            /*
             * Dejamos las series preparadas para empezar de cero.
             */
            $connection->executeStatement(
                'UPDATE serie_documento SET ultimo_numero = 0, actualizado_en = NULL'
            );

            /*
             * Saldo inicial de la caja de pruebas: 1.000 € en banco y
             * 1.000 € en efectivo, fechados el primer día del mes actual.
             */
            $connection->executeStatement(
                "INSERT INTO tiposmovimiento (descripcion_tm, patron_busqueda)
                 SELECT 'Inicial', NULL FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM tiposmovimiento WHERE descripcion_tm = 'Inicial')"
            );
            $tipoInicial = $connection->fetchOne(
                "SELECT id FROM tiposmovimiento WHERE descripcion_tm = 'Inicial' LIMIT 1"
            );
            $fechaInicial = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
            $timestampInicial = $fechaInicial . ' 00:00:00';

            $connection->insert('banco', [
                'categoria_bn' => $tipoInicial,
                'importe_bn' => 1000,
                'concepto_bn' => 'Saldo inicial desarrollo',
                'fecha_bn' => $fechaInicial,
                'timestamp_bn' => $timestampInicial,
                'conciliado' => 1,
            ]);
            $connection->insert('efectivo', [
                'tipoEf' => $tipoInicial,
                'concepto_ef' => 'Saldo inicial desarrollo',
                'fecha_ef' => $fechaInicial,
                'importe_ef' => 1000,
                'timestamp_ef' => $timestampInicial,
                'presupuestoef_id' => null,
            ]);

            $output->writeln('<info>Series reiniciadas a 0.</info>');
            $output->writeln('<info>Saldo inicial creado: 1.000 € en banco y 1.000 € en efectivo.</info>');

        } catch (\Throwable $e) {

            $output->writeln('');
            $output->writeln(
                '<error>Error reiniciando la base de datos:</error>'
            );

            $output->writeln(
                '<error>'.$e->getMessage().'</error>'
            );

            return Command::FAILURE;

        } finally {

            /*
             * IMPORTANTÍSIMO:
             *
             * Pase lo que pase intentamos volver a activar las FK.
             */
            try {
                $connection->executeStatement(
                    'SET FOREIGN_KEY_CHECKS = 1'
                );
            } catch (\Throwable) {
                // No hacemos nada aquí para no ocultar el error original.
            }
        }

        /*
         * Limpiamos el EntityManager porque acabamos de modificar
         * directamente la BBDD por DBAL.
         */
        $this->entityManager->clear();

        $output->writeln('');
        $output->writeln(
            '<info>=============================================</info>'
        );
        $output->writeln(
            '<info> Base de datos reiniciada correctamente.</info>'
        );
        $output->writeln(
            '<info>=============================================</info>'
        );
        $output->writeln('');

        $output->writeln('Tablas conservadas:');

        foreach ($tablasConservar as $tabla) {
            $output->writeln('  ✓ '.$tabla);
        }

        $output->writeln('');

        return Command::SUCCESS;
    }
}
