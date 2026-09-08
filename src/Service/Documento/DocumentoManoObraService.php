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
            1 => [
                26, // Demolición y retirada
                27, // Preparación de paredes
                28, // Alicatado
                30, // Colocación de plato de ducha
                41, // Gestión de residuos de obra
                42, // Medios para retirada de escombros
            ],

            2 => [
                34, // Conexión de plato de ducha
                35, // Instalación de grifería de ducha
            ],

            8 => [
                40, // Instalación de mampara
                43, // Protección de zonas de paso
                44, // Protección de zonas comunes
                45, // Acarreo de materiales
                47, // Limpieza de obra
            ],
        ],

        'bano' => [
            1 => [
                26, // Demolición y retirada
                27, // Preparación de paredes
                28, // Alicatado
                29, // Pavimentado
                41, // Gestión de residuos de obra
                42, // Medios para retirada de escombros
            ],

            2 => [
                31, // Instalación de lavabo
                32, // Instalación de inodoro
                33, // Instalación de bidé
                34, // Conexión de plato de ducha
                35, // Instalación de grifería de ducha
            ],

            5 => [
                38, // Punto eléctrico para espejo
                39, // Instalación de iluminación
            ],

            3 => [
                36, // Pintura de techo
            ],

            8 => [
                40, // Instalación de mampara
                43, // Protección de zonas de paso
                44, // Protección de zonas comunes
                45, // Acarreo de materiales
                47, // Limpieza de obra
            ],
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