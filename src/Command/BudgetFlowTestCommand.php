<?php

namespace App\Command;

use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:budget-flow:test',
    description: 'Prueba la comunicación del ERP con BudgetFlow',
)]
final class BudgetFlowTestCommand extends Command
{
    public function __construct(
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'codigo',
            InputArgument::REQUIRED,
            'Código del configurador'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $codigo = $input->getArgument('codigo');

        $io->title('Prueba de conexión con BudgetFlow');

        try {
            $configurador = $this->budgetFlowConfiguratorService->obtenerConfigurador($codigo);

            $resultadoValidacion = 
            $this->budgetFlowConfiguratorService->validar($codigo,
                [
                    'ancho' => 80,
                    'largo' => 120,
                ]
        );

        dump($resultadoValidacion);

            $resultado = $this->budgetFlowConfiguratorService->generar(
                $codigo,
                [
                    'ancho' => 80,
                    'largo' => 120,
                ]
            );

            dump($resultado);            


            $io->success('Comunicación con BudgetFlow correcta.');

            $io->writeln(sprintf(
                'Código: <info>%s</info>',
                $configurador['codigo'] ?? '-'
            ));

            $io->writeln(sprintf(
                'Nombre: <info>%s</info>',
                $configurador['nombre'] ?? '-'
            ));

            $io->writeln(sprintf(
                'Tipo: <info>%s</info>',
                $configurador['tipo'] ?? '-'
            ));

            $io->writeln(sprintf(
                'Versión: <info>%s</info>',
                $configurador['version'] ?? '-'
            ));

            $campos = $configurador['campos'] ?? [];

            if ($campos !== []) {
                $io->section('Campos');

                foreach ($campos as $campo) {
                    $io->writeln(
                        ' - '.($campo['codigo'] ?? 'sin_codigo')
                    );
                }
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error(
                'No se ha podido comunicar con BudgetFlow: '.$e->getMessage()
            );

            return Command::FAILURE;
        }
    }
}