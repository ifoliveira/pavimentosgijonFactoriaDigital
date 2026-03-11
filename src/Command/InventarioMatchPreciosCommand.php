<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InventarioMatchPreciosCommand extends Command
{
    protected static $defaultName = 'app:inventario:match-precios';

    private Connection $conn;

    public function __construct(Connection $conn)
    {
        parent::__construct();
        $this->conn = $conn;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Busca el producto más parecido y añade precio al CSV')
            ->addArgument('csv', InputArgument::REQUIRED, 'Ruta del CSV de entrada');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csvPath = $input->getArgument('csv');

        if (!file_exists($csvPath)) {
            $output->writeln('<error>No existe el CSV</error>');
            return Command::FAILURE;
        }

        $productos = $this->conn->fetchAllAssociative("
            SELECT id, descripcion_pd, precio_pd
            FROM productos
            WHERE precio_pd > 0
        ");

        $inputFile = fopen($csvPath, 'r');

        $outputFile = fopen(
            str_replace('.csv', '_precio.csv', $csvPath),
            'w'
        );

        fputcsv($outputFile, [
            'producto',
            'categoria',
            'cantidad',
            'precio',
            'producto_bd',
            'score'
        ]);

        while (($row = fgetcsv($inputFile)) !== false) {

            $producto = $row[0];
            $categoria = $row[1];
            $cantidad = $row[2];

            $productoNormal = $this->limpiar($producto);

            $mejorScore = 0;
            $mejorProducto = null;

            foreach ($productos as $p) {

                $bd = $this->limpiar($p['descripcion_pd']);

                similar_text($productoNormal, $bd, $score);

                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejorProducto = $p;
                }
            }

            $precio = $mejorProducto['precio_pd'] ?? null;
            $nombreBD = $mejorProducto['descripcion_pd'] ?? null;

            fputcsv($outputFile, [
                $producto,
                $categoria,
                $cantidad,
                $precio,
                $nombreBD,
                round($mejorScore, 2)
            ]);
        }

        fclose($inputFile);
        fclose($outputFile);

        $output->writeln('<info>CSV generado correctamente</info>');

        return Command::SUCCESS;
    }

    private function limpiar(string $txt): string
    {
        $txt = strtolower($txt);

        $quitar = [
            'cromo',
            'inox',
            'blanco',
            'negro',
            'cm',
            'ref',
            'serie'
        ];

        foreach ($quitar as $q) {
            $txt = str_replace($q, '', $txt);
        }

        return trim($txt);
    }
}