<?php

namespace App\Service\FichaVisita;

use App\Entity\Clientes;
use App\Entity\Documento;
use App\Entity\Proyecto;
use App\Service\BudgetFlow\BudgetFlowConfiguratorService;
use App\Service\Documento\BudgetFlowDocumentoService;
use App\Service\Proyecto\ProyectoPresupuestoFactory;
use Doctrine\ORM\EntityManagerInterface;

class PresupuestoDesdeFotoService
{
    public const COMPLETADO = 'completado';
    public const PENDIENTE_CLIENTE = 'pendiente_cliente';
    public const REVISION_TECNICA = 'revision_tecnica';
    public const BUDGETFLOW_ERROR = 'budgetflow_error';
    public const CLIENTE_AMBIGUO = 'cliente_ambiguo';

    public function __construct(
        private readonly TelefonoMatcher $telefonoMatcher,
        private readonly FichaVisitaConjuntoDuchaAdapter $conjuntoDuchaAdapter,
        private readonly BudgetFlowConfiguratorService $budgetFlowConfiguratorService,
        private readonly BudgetFlowDocumentoService $budgetFlowDocumentoService,
        private readonly ProyectoPresupuestoFactory $proyectoPresupuestoFactory,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{status: string, datos: array, candidatos?: array, warning?: string, proyecto?: Proyecto, documento?: Documento, adaptacion?: array, ejecucion?: array}
     */
    public function ejecutar(
        array $datos,
        string $obra,
        string $descripcion,
        ?string $direccionObra,
        ?string $fichaFotoPath,
        ?int $clienteSeleccionadoId = null
    ): array {
        $datos = $this->normalizarDatos($datos);
        $pendientes = $datos['pendientes'];
        $nombre = trim((string) ($datos['nombre_cliente'] ?? ''));
        $telefono = trim((string) ($datos['telefono'] ?? ''));
        $direccionObra = trim((string) ($direccionObra ?? $datos['direccion_obra'] ?? ''));
        $obra = trim($obra);

        if ($obra === '') {
            $pendientes[] = 'obra';
        }
        if ($nombre === '') {
            $pendientes[] = 'nombre_cliente';
        }
        if ($telefono === '' || !$this->telefonoMatcher->esValido($telefono)) {
            $pendientes[] = 'telefono';
        }

        $datos['pendientes'] = array_values(array_unique(array_filter($pendientes, 'is_string')));
        $datos['direccion_obra'] = $direccionObra !== '' ? $direccionObra : null;

        if ($datos['pendientes'] !== []) {
            return [
                'status' => self::PENDIENTE_CLIENTE,
                'datos' => $datos,
                'warning' => 'Completa los datos pendientes antes de crear el proyecto.',
            ];
        }

        $cliente = $this->resolverCliente($nombre, $telefono, $direccionObra, $clienteSeleccionadoId);
        if (is_array($cliente)) {
            return [
                'status' => self::CLIENTE_AMBIGUO,
                'datos' => $datos,
                'candidatos' => $cliente['candidatos'],
                'warning' => $cliente['warning'],
            ];
        }

        $adaptacion = $this->adaptar($datos);
        $datosProyecto = $datos;
        $datosProyecto['budgetflow_adaptacion']['conjunto_ducha'] = $adaptacion;

        $resultado = $this->proyectoPresupuestoFactory->crear(
            $cliente,
            $obra,
            $descripcion,
            null,
            $direccionObra !== '' ? $direccionObra : null,
            $fichaFotoPath,
            $datosProyecto
        );

        if ($this->requiereRevision($adaptacion)) {
            return [
                'status' => self::REVISION_TECNICA,
                'datos' => $datosProyecto,
                'proyecto' => $resultado['proyecto'],
                'documento' => $resultado['presupuesto'],
                'adaptacion' => $adaptacion,
                'warning' => 'Revisa los datos leídos de la ficha antes de generar las líneas.',
            ];
        }

        try {
            $ejecucion = $this->budgetFlowDocumentoService->generarEIncorporar(
                $resultado['presupuesto'],
                'conjunto_ducha',
                $adaptacion['valores'] ?? [],
                $adaptacion['componentes_excluidos'] ?? [],
                true
            );
        } catch (\Throwable $e) {
            return [
                'status' => self::BUDGETFLOW_ERROR,
                'datos' => $datosProyecto,
                'proyecto' => $resultado['proyecto'],
                'documento' => $resultado['presupuesto'],
                'adaptacion' => $adaptacion,
                'warning' => 'No se pudieron generar las líneas automáticamente: '.$e->getMessage(),
            ];
        }

        if (!($ejecucion['generado'] ?? false)) {
            return [
                'status' => self::REVISION_TECNICA,
                'datos' => $datosProyecto,
                'proyecto' => $resultado['proyecto'],
                'documento' => $resultado['presupuesto'],
                'adaptacion' => $adaptacion,
                'ejecucion' => $ejecucion,
                'warning' => 'BudgetFlow necesita revisión antes de generar las líneas.',
            ];
        }

        return [
            'status' => self::COMPLETADO,
            'datos' => $datosProyecto,
            'proyecto' => $resultado['proyecto'],
            'documento' => $resultado['presupuesto'],
            'adaptacion' => $adaptacion,
            'ejecucion' => $ejecucion,
        ];
    }

    public function adaptar(array $datos): array
    {
        try {
            $configurador = $this->budgetFlowConfiguratorService->obtenerConfiguradorParaFormulario('conjunto_ducha');

            return $this->conjuntoDuchaAdapter->adaptar($datos, $configurador);
        } catch (\Throwable $e) {
            return [
                'valores' => [],
                'avisos' => ['No se pudo adaptar la ficha a BudgetFlow: '.$e->getMessage()],
                'pendientes' => [],
                'observaciones' => [],
                'sin_correspondencia' => [],
                'componentes_excluidos' => [],
                'procedencia' => [],
                'mapeo' => [],
            ];
        }
    }

    public function requiereRevision(array $adaptacion): bool
    {
        return ($adaptacion['pendientes'] ?? []) !== []
            || ($adaptacion['avisos'] ?? []) !== [];
    }

    private function resolverCliente(string $nombre, string $telefono, string $direccionObra, ?int $clienteSeleccionadoId): Clientes|array
    {
        $matches = $this->telefonoMatcher->buscarClientes($telefono);

        if ($clienteSeleccionadoId !== null && $clienteSeleccionadoId > 0) {
            $cliente = $this->em->getRepository(Clientes::class)->find($clienteSeleccionadoId);
            if (!$cliente instanceof Clientes) {
                return ['candidatos' => $matches, 'warning' => 'El cliente seleccionado no existe.'];
            }

            return $cliente;
        }

        if (count($matches) > 1) {
            return ['candidatos' => $matches, 'warning' => 'Hay varias personas con ese teléfono. Selecciona el cliente correcto.'];
        }

        if (count($matches) === 1) {
            $cliente = $matches[0];
            if (!$this->nombrePareceElMismo($nombre, $cliente)) {
                return ['candidatos' => $matches, 'warning' => 'El teléfono coincide, pero el nombre parece distinto. Selecciona el cliente existente o corrige los datos.'];
            }

            return $cliente;
        }

        $cliente = new Clientes();
        $cliente->setNombreCl($nombre);
        $cliente->setTelefono1Cl($this->telefonoMatcher->normalizarParaGuardar($telefono));
        $cliente->setDireccionCl($direccionObra !== '' ? $direccionObra : null);
        $cliente->setTimestampaltaCl(new \DateTime());

        return $cliente;
    }

    private function nombrePareceElMismo(string $nombre, Clientes $cliente): bool
    {
        $nombre = $this->normalizarNombre($nombre);
        $existente = $this->normalizarNombre(trim((string) $cliente->getNombreCl().' '.(string) $cliente->getApellidosCl()));

        if ($nombre === '' || $existente === '') {
            return false;
        }

        if ($nombre === $existente || str_contains($nombre, $existente) || str_contains($existente, $nombre)) {
            return true;
        }

        $tokensNombre = array_filter(explode(' ', $nombre), static fn (string $token): bool => strlen($token) >= 3);
        $tokensExistente = array_filter(explode(' ', $existente), static fn (string $token): bool => strlen($token) >= 3);

        return count(array_intersect($tokensNombre, $tokensExistente)) > 0;
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = strtolower(trim($nombre));
        $nombre = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre;

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $nombre));
    }

    private function normalizarDatos(array $datos): array
    {
        return [
            'nombre_cliente' => $this->stringOrNull($datos['nombre_cliente'] ?? null),
            'telefono' => $this->stringOrNull($datos['telefono'] ?? null),
            'direccion_obra' => $this->stringOrNull($datos['direccion_obra'] ?? null),
            'pendientes' => is_array($datos['pendientes'] ?? null) ? array_values(array_filter($datos['pendientes'], 'is_string')) : [],
            'tecnicos' => is_array($datos['tecnicos'] ?? null) ? $datos['tecnicos'] : [],
            'casillas' => is_array($datos['casillas'] ?? null) ? $datos['casillas'] : [],
            'contradicciones' => is_array($datos['contradicciones'] ?? null) ? $datos['contradicciones'] : [],
            'observaciones' => is_array($datos['observaciones'] ?? null) ? $datos['observaciones'] : [],
            'original' => is_array($datos['original'] ?? null) ? $datos['original'] : $datos,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
