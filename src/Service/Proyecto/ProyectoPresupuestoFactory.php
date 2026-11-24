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
     * El presupuesto inicial se crea siempre, incluso si el cliente todavía
     * no se conoce. La aceptación del presupuesto es la operación que exige
     * identificar al cliente.
     *
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

            $nombreManual = trim((string) $proyecto->getNombre());

            if (!$cliente instanceof Clientes && $nombreManual === '') {
                throw new \RuntimeException('Selecciona un cliente o escribe un nombre descriptivo para identificar la obra.');
            }

            if ($cliente instanceof Clientes && $cliente->getId() === null) {
                $this->em->persist($cliente);
            }

            $proyecto->setNombre(
                $cliente instanceof Clientes
                    ? $this->construirNombre($cliente, $nombreManual, $tipoObra)
                    : $nombreManual
            );
            $proyecto->setFichaFotoPath($fichaFotoPath);
            $proyecto->setDireccionObraExtraida($direccionObraExtraida);
            $proyecto->setFichaDatosExtraidos($fichaDatosExtraidos !== [] ? $fichaDatosExtraidos : null);

            $this->em->persist($proyecto);
            $this->em->flush();

            $presupuesto = $this->documentoCrearService->crearDocumento(
                'presupuesto',
                $cliente instanceof Clientes ? $cliente->getId() : null
            );
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
            'plato_ducha' => 'Plato de ducha',
            'bano' => 'Baño',
            'mampara' => 'Mampara',
            'mueble' => 'Mueble',
            'otros' => 'Otros',
            // Valores conservados para proyectos creados antes de ampliar el catálogo.
            'ducha' => 'Cambio bañera por ducha',
            'bano_completo' => 'Baño completo',
            'otro' => 'Otro',
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

        if ($partes === []) {
            $nombreCliente = trim(($cliente->getNombreCl() ?? '') . ' ' . ($cliente->getApellidosCl() ?? ''));
            $partes[] = $nombreCliente !== '' ? 'Proyecto de ' . $nombreCliente : 'Proyecto sin descripción';
        }

        return implode(' - ', $partes);
    }
}
