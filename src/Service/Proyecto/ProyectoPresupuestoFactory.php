<?php

namespace App\Service\Proyecto;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Service\Documento\DocumentoCrearService;
use Doctrine\ORM\EntityManagerInterface;

final class ProyectoPresupuestoFactory
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentoCrearService $documentoCrearService,
    ) {
    }

    /**
     * @return array{proyecto: Proyecto, presupuesto: Documento}
     */
    public function crear(
        Clientes $cliente,
        string $nombreManual,
        ?string $descripcion,
        ?string $tipoObra = null,
        ?string $direccionObraExtraida = null,
        ?string $fichaFotoPath = null,
        array $fichaDatosExtraidos = [],
    ): array {
        $proyecto = new Proyecto();
        $proyecto->setCliente($cliente);
        $proyecto->setNombre($nombreManual);
        $proyecto->setNotas($descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null);

        return $this->crearDesdeProyecto(
            $proyecto,
            $tipoObra,
            $direccionObraExtraida,
            $fichaFotoPath,
            $fichaDatosExtraidos
        );
    }

    /**
     * @return array{proyecto: Proyecto, presupuesto: Documento}
     */
    public function crearDesdeProyecto(
        Proyecto $proyecto,
        ?string $tipoObra = null,
        ?string $direccionObraExtraida = null,
        ?string $fichaFotoPath = null,
        array $fichaDatosExtraidos = [],
    ): array {
        return $this->em->wrapInTransaction(function () use (
            $proyecto,
            $tipoObra,
            $direccionObraExtraida,
            $fichaFotoPath,
            $fichaDatosExtraidos
        ): array {
            $cliente = $proyecto->getCliente();

            if (!$cliente instanceof Clientes) {
                throw new \RuntimeException('El proyecto necesita un cliente para crear el presupuesto inicial.');
            }

            if ($cliente->getId() === null) {
                $this->em->persist($cliente);
            }

            $proyecto->setNombre($this->construirNombre($cliente, (string) $proyecto->getNombre(), $tipoObra));
            $proyecto->setFichaFotoPath($fichaFotoPath);
            $proyecto->setDireccionObraExtraida($direccionObraExtraida);
            $proyecto->setFichaDatosExtraidos($fichaDatosExtraidos !== [] ? $fichaDatosExtraidos : null);

            $this->em->persist($proyecto);
            $this->em->flush();

            $presupuesto = $this->documentoCrearService->crearDocumento('presupuesto', $cliente->getId());
            $presupuesto->setProyecto($proyecto);

            $this->em->flush();

            return [
                'proyecto' => $proyecto,
                'presupuesto' => $presupuesto,
            ];
        });
    }

    public function construirNombre(Clientes $cliente, string $nombreManual, ?string $tipoObra = null): string
    {
        $prefijo = match ($tipoObra) {
            'ducha' => 'Cambio bañera por ducha',
            'bano_completo' => 'Baño completo',
            default => null,
        };

        $direccion = trim((string) $cliente->getDireccionCl());
        $partes = [];

        if ($prefijo) {
            $partes[] = $prefijo;
        }

        $nombreManual = trim($nombreManual);
        if ($nombreManual !== '') {
            $partes[] = $nombreManual;
        }

        if ($direccion !== '') {
            $partes[] = $direccion;
        }

        return implode(' - ', $partes);
    }
}
