<?php

namespace App\Command;

use App\Repository\CestasRepository;
use App\Service\MigracionCestaTicketService;
use App\Service\Documento\SerieService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrar-cestas-tickets',
    description: 'Migra cestas antiguas a documentos tipo ticket'
)]
final class MigrarCestasATicketsCommand extends Command
{
    public function __construct(
        private readonly CestasRepository $cestasRepository,
        private readonly MigracionCestaTicketService $migracionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'ids',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'IDs de las cestas que quieres migrar'
            )
            ->addOption(
                'simular',
                null,
                InputOption::VALUE_NONE,
                'Ejecuta la migración pero deshace todos los cambios'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $ids = array_values(array_unique(array_map(
            'intval',
            $input->getArgument('ids')
        )));

        if ($ids === []) {
            $io->error('No has indicado ningún ID de cesta.');

            return Command::FAILURE;
        }

        $cestas = $this->cestasRepository
            ->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('c.fechaCs', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (count($cestas) !== count($ids)) {
            $idsEncontrados = array_map(
                static fn ($cesta): int => $cesta->getId(),
                $cestas
            );

            $idsNoEncontrados = array_diff($ids, $idsEncontrados);

            $io->error(sprintf(
                'No se encontraron estas cestas: %s',
                implode(', ', $idsNoEncontrados)
            ));

            return Command::FAILURE;
        }

        $simular = (bool) $input->getOption('simular');
        $conexion = $this->entityManager->getConnection();

        $conexion->beginTransaction();

        try {
            foreach ($cestas as $cesta) {
                $ticket = $this->migracionService->migrar($cesta);

                /*
                 * Flush individual para obtener el ID y detectar
                 * cualquier error exactamente en esa cesta.
                 */
                $this->entityManager->flush();

                $io->writeln(sprintf(
                    '<info>Cesta %d</info> → <comment>%s</comment> | %s | %s € | %s',
                    $cesta->getId(),
                    $ticket->getNumeroFormateado(),
                    $ticket->getFechaEmision()?->format('d/m/Y'),
                    $ticket->getTotal(),
                    'sin cobro'
                ));
            }

            if ($simular) {
                $conexion->rollBack();

                $io->warning(
                    'Simulación terminada. No se ha guardado ningún cambio.'
                );

                return Command::SUCCESS;
            }

            $conexion->commit();

            $io->success(sprintf(
                'Se han migrado correctamente %d cestas.',
                count($cestas)
            ));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ($conexion->isTransactionActive()) {
                $conexion->rollBack();
            }

            $io->error([
                'La migración se ha cancelado.',
                $exception->getMessage(),
                'No se ha guardado ninguna de las cestas.',
            ]);

            return Command::FAILURE;
        }
    }
}