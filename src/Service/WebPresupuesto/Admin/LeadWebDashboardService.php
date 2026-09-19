<?php

namespace App\Service\WebPresupuesto\Admin;

use App\Entity\PresupuestoWeb;
use App\Entity\PresupuestoWebInteraccion;
use App\Repository\PresupuestoWebInteraccionRepository;
use App\Repository\PresupuestoWebRepository;

final class LeadWebDashboardService
{
    public function __construct(
        private readonly PresupuestoWebRepository $presupuestoRepository,
        private readonly PresupuestoWebInteraccionRepository $interaccionRepository,
        private readonly LeadWebEstadoResolver $estadoResolver,
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, resumen: array<string, int>, filtros: array<string, string>, filtro: string, busqueda: string, orden: string}
     */
    public function listar(string $filtro = 'requieren_intervencion', string $busqueda = '', string $orden = 'actividad'): array
    {
        $presupuestos = $this->presupuestoRepository->createQueryBuilder('p')
            ->leftJoin('p.lead', 'l')->addSelect('l')
            ->leftJoin('p.comunicaciones', 'c')->addSelect('c')
            ->leftJoin('p.accesos', 'a')->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        $items = [];

        foreach ($presupuestos as $presupuesto) {
            if (!$presupuesto instanceof PresupuestoWeb) {
                continue;
            }

            if ($busqueda !== '' && stripos($presupuesto->getLead()->getEmail(), $busqueda) === false) {
                continue;
            }

            $interacciones = $this->interaccionRepository->findByPresupuestoOrdenadas($presupuesto);
            $decision = $this->estadoResolver->resolver($presupuesto, $interacciones);
            $ultimaInteraccion = $interacciones[0] ?? null;
            $item = [
                'presupuesto' => $presupuesto,
                'email' => $presupuesto->getLead()->getEmail(),
                'tipoLabel' => $this->tipoLabel($presupuesto->getTipoPresupuesto()),
                'importe' => $this->importe($presupuesto->getTotal()),
                'createdAt' => $presupuesto->getCreatedAt(),
                'visualizaciones' => $presupuesto->getNumeroVisualizaciones(),
                'ultimaVisualizacionAt' => $presupuesto->getUltimaVisualizacionAt(),
                'ultimaInteraccion' => $ultimaInteraccion,
                'ultimaInteraccionLabel' => $ultimaInteraccion ? $this->interaccionLabel($ultimaInteraccion) : 'Sin interacción',
                'decision' => $decision,
                'actividadAt' => $this->actividad($presupuesto, $ultimaInteraccion),
            ];

            if ($this->pasaFiltro($item, $filtro)) {
                $items[] = $item;
            }
        }

        usort($items, fn(array $a, array $b): int => $this->comparar($a, $b, $orden));

        return [
            'items' => $items,
            'resumen' => $this->resumen($presupuestos),
            'filtros' => $this->filtros(),
            'filtro' => $filtro,
            'busqueda' => $busqueda,
            'orden' => $orden,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detalle(PresupuestoWeb $presupuesto): array
    {
        $interacciones = $this->interaccionRepository->findByPresupuestoOrdenadas($presupuesto);
        $decision = $this->estadoResolver->resolver($presupuesto, $interacciones);
        $ultimaInteraccion = $decision->interaccionBase ?? ($interacciones[0] ?? null);

        return [
            'presupuesto' => $presupuesto,
            'email' => $presupuesto->getLead()->getEmail(),
            'tipoLabel' => $this->tipoLabel($presupuesto->getTipoPresupuesto()),
            'importe' => $this->importe($presupuesto->getTotal()),
            'decision' => $decision,
            'interacciones' => $interacciones,
            'ultimaInteraccion' => $ultimaInteraccion,
            'ultimaInteraccionLabel' => $ultimaInteraccion ? $this->interaccionLabel($ultimaInteraccion) : 'Sin interacción',
        ];
    }

    private function pasaFiltro(array $item, string $filtro): bool
    {
        $estado = $item['decision']->estado;

        return match ($filtro) {
            'todos' => true,
            'requieren_intervencion' => $item['decision']->requiereIntervencion,
            'visita_solicitada' => $estado === 'VISITA_SOLICITADA',
            'contacto_solicitado' => $estado === 'CONTACTO_SOLICITADO',
            'duda_pendiente' => $estado === 'DUDA_PENDIENTE',
            'cambio_solicitado' => $estado === 'CAMBIO_SOLICITADO',
            'seguimiento_automatico' => $item['decision']->siguienteAccion === 'SEGUIMIENTO_AUTOMATICO',
            'cerrados' => $estado === 'CERRADO',
            default => $item['decision']->requiereIntervencion,
        };
    }

    private function comparar(array $a, array $b, string $orden): int
    {
        return match ($orden) {
            'creacion' => $b['createdAt'] <=> $a['createdAt'],
            'importe' => (float) ($b['presupuesto']->getTotal() ?? 0) <=> (float) ($a['presupuesto']->getTotal() ?? 0),
            'ultima_visualizacion' => ($b['ultimaVisualizacionAt'] ?? new \DateTimeImmutable('@0')) <=> ($a['ultimaVisualizacionAt'] ?? new \DateTimeImmutable('@0')),
            default => $b['actividadAt'] <=> $a['actividadAt'],
        };
    }

    private function actividad(PresupuestoWeb $presupuesto, ?PresupuestoWebInteraccion $interaccion): \DateTimeImmutable
    {
        $fechas = array_filter([
            $presupuesto->getCreatedAt(),
            $presupuesto->getUltimaVisualizacionAt(),
            $interaccion?->getCreatedAt(),
        ]);

        usort($fechas, static fn(\DateTimeImmutable $a, \DateTimeImmutable $b): int => $b <=> $a);

        return $fechas[0];
    }

    /**
     * @param PresupuestoWeb[] $presupuestos
     * @return array<string, int>
     */
    private function resumen(array $presupuestos): array
    {
        $resumen = [
            'activos' => 0,
            'requierenIntervencion' => 0,
            'visitasSolicitadas' => 0,
            'contactosSolicitados' => 0,
            'seguimientoAutomatico' => 0,
        ];

        foreach ($presupuestos as $presupuesto) {
            if (!$presupuesto instanceof PresupuestoWeb) {
                continue;
            }

            $decision = $this->estadoResolver->resolver($presupuesto, $this->interaccionRepository->findByPresupuestoOrdenadas($presupuesto));

            if ($decision->estado !== 'CERRADO') {
                $resumen['activos']++;
            }

            if ($decision->requiereIntervencion) {
                $resumen['requierenIntervencion']++;
            }

            if ($decision->estado === 'VISITA_SOLICITADA') {
                $resumen['visitasSolicitadas']++;
            }

            if ($decision->estado === 'CONTACTO_SOLICITADO') {
                $resumen['contactosSolicitados']++;
            }

            if ($decision->siguienteAccion === 'SEGUIMIENTO_AUTOMATICO') {
                $resumen['seguimientoAutomatico']++;
            }
        }

        return $resumen;
    }

    /**
     * @return array<string, string>
     */
    private function filtros(): array
    {
        return [
            'todos' => 'Todos',
            'requieren_intervencion' => 'Requieren intervención',
            'visita_solicitada' => 'Visita solicitada',
            'contacto_solicitado' => 'Contacto solicitado',
            'duda_pendiente' => 'Duda pendiente',
            'cambio_solicitado' => 'Cambio solicitado',
            'seguimiento_automatico' => 'En seguimiento automático',
            'cerrados' => 'Cerrados',
        ];
    }

    private function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'ducha' => 'Cambio bañera → ducha',
            default => ucfirst(str_replace('_', ' ', $tipo)),
        };
    }

    private function interaccionLabel(PresupuestoWebInteraccion $interaccion): string
    {
        return match ($interaccion->getTipo()->value) {
            'duda' => 'DUDA',
            'cambio' => 'QUIERE CAMBIAR ALGO',
            'solicita_contacto' => 'QUIERE QUE HABLEMOS',
            'solicita_medicion' => 'QUIERE QUE VAYAMOS A MEDIR',
            'no_continua' => 'NO CONTINÚA',
            default => strtoupper($interaccion->getTipo()->value),
        };
    }

    private function importe(?string $total): string
    {
        if ($total === null || !is_numeric($total)) {
            return 'Sin importe';
        }

        return number_format((float) $total, 2, ',', '.').' €';
    }
}
