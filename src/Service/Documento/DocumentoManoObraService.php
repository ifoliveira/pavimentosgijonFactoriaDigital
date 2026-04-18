<?php

namespace App\Service\Documento;

use App\Entity\Documento;
use App\Entity\ManoObra;
use App\Repository\TextoManoObraRepository;
use App\Repository\TipoManoObraRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ManoObraTextoSeleccionado;

class DocumentoManoObraService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TipoManoObraRepository $tipoManoObraRepository,
        private TextoManoObraRepository $textoManoObraRepository,
    ) {}

    public function guardarDesdeSeleccion(Documento $documento, array $selecciones, array $textoManual = []): void
    {
        // Mapear ManoObra existentes por tipo
        $existentesPorTipo = [];
        foreach ($documento->getManoObra() as $mo) {
            if ($mo->getCategoriaMo()) {
                $existentesPorTipo[$mo->getCategoriaMo()->getId()] = $mo;
            }
        }

        $tipos = $this->tipoManoObraRepository->findAll();

        foreach ($tipos as $tipo) {
            $tipoId = $tipo->getId();
            $idsTextos = $selecciones[$tipoId] ?? [];
            $idsTextos = array_values(array_unique(array_filter(array_map('intval', $idsTextos))));

            /** @var ManoObra|null $manoObra */
            $manoObra = $existentesPorTipo[$tipoId] ?? null;
            $textoManualCategoria = trim((string) ($textoManual[$tipoId] ?? ''));

            // Si no hay selecciones y no hay texto manual → eliminar categoría
            if (empty($idsTextos) && $textoManualCategoria === '') {
                if ($manoObra) {
                    $this->em->remove($manoObra);
                }
                continue;
            }

            // Crear ManoObra si no existe
            if (!$manoObra) {
                $manoObra = new ManoObra();
                $manoObra->setDocumentoMo($documento);
                $manoObra->setCategoriaMo($tipo);
                $this->em->persist($manoObra);
            }
            $manoObra->setTextoMo($textoManualCategoria !== '' ? $textoManualCategoria : null);

            // Limpiar selecciones actuales
            $manoObra->clearSeleccionesTexto();

            // Cargar textos en el mismo orden que llegan del front
            $textos = $this->textoManoObraRepository->findBy(['id' => $idsTextos]);

            usort($textos, function ($a, $b) use ($idsTextos) {
                return array_search($a->getId(), $idsTextos, true)
                    <=> array_search($b->getId(), $idsTextos, true);
            });

            // Insertar nuevas selecciones con orden
            $orden = 1;
            foreach ($textos as $texto) {
                $sel = new ManoObraTextoSeleccionado();
                $sel->setManoObra($manoObra);
                $sel->setTextoManoObra($texto);
                $sel->setOrden($orden++);
                $this->em->persist($sel);
            }
        }



        $this->em->flush();
    }

    private function tieneTextoManual(ManoObra $manoObra): bool
    {
        return trim((string) $manoObra->getTextoMo()) !== '';
    }

    private const PRESETS = [

        'ducha' => [
            1 => [3,4,5,6],          // albañilería
            2 => [7,8,14,15],        // fontanería
            5 => [17,18],            // electricidad
            8 => [19,23],            // otros
        ],

        'bano' => [
            1 => [3,4,5],            
            2 => [7,8,13,14,15],     
            5 => [17,18],            
            4 => [20],               
            3 => [21],               
            8 => [23],               
        ],
    ];

    public function aplicarPreset(Documento $documento, string $preset): void
    {
        if (!isset(self::PRESETS[$preset])) {
            return;
        }

        $selecciones = self::PRESETS[$preset];

        // reutilizas tu método existente
        $this->guardarDesdeSeleccion($documento, $selecciones, []);
    }    

}