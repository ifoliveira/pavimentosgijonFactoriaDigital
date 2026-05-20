<?php

namespace App\Service;

use App\Entity\CatalogoProducto;
use App\Entity\CatalogoProductoConfiguracion;
use App\Repository\CatalogoProductoConfiguracionRepository;

class CatalogoProductoSelectorService
{
    public function __construct(
        private CatalogoProductoConfiguracionRepository $configuracionRepository
    ) {}

    /**
     * Busca la configuración recomendada compatible con el configurador.
     *
     * Ejemplo:
     * - configuradorCodigo: ducha
     * - uso: plato
     * - tipo: resina
     * - largo: 170
     * - ancho: 70
     */
    public function buscarRecomendado(
        string $configuradorCodigo,
        string $uso,
        ?string $tipo = null,
        ?float $largo = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $colorCodigo = null
    ): ?CatalogoProductoConfiguracion {
        $qb = $this->configuracionRepository->createQueryBuilder('c')
            ->join('c.producto', 'p')
            ->addSelect('p')
            ->where('c.configuradorCodigo = :configuradorCodigo')
            ->andWhere('c.uso = :uso')
            ->andWhere('c.activo = true')
            ->andWhere('p.activo = true')
            ->andWhere('p.visiblePresupuesto = true')
            ->setParameter('configuradorCodigo', $configuradorCodigo)
            ->setParameter('uso', $uso);

        if ($tipo !== null && $tipo !== '') {
            $qb
                ->andWhere('(c.tipo = :tipo OR c.tipo IS NULL)')
                ->setParameter('tipo', $tipo);
        }

        if ($largo !== null) {
            $qb
                ->andWhere('(c.largoMin IS NULL OR c.largoMin <= :largo)')
                ->andWhere('(c.largoMax IS NULL OR c.largoMax >= :largo)')
                ->setParameter('largo', $largo);
        }

        if ($ancho !== null) {
            $qb
                ->andWhere('(c.anchoMin IS NULL OR c.anchoMin <= :ancho)')
                ->andWhere('(c.anchoMax IS NULL OR c.anchoMax >= :ancho)')
                ->setParameter('ancho', $ancho);
        }

        if ($alto !== null) {
            $qb
                ->andWhere('(c.altoMin IS NULL OR c.altoMin <= :alto)')
                ->andWhere('(c.altoMax IS NULL OR c.altoMax >= :alto)')
                ->setParameter('alto', $alto);
        }

        $configuraciones = $qb
            ->orderBy('c.recomendado', 'DESC')
            ->addOrderBy('c.prioridad', 'ASC')
            ->addOrderBy('p.precioVenta', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($configuraciones as $configuracion) {
            if ($colorCodigo !== null) {
                $atributos = $configuracion->getCondiciones() ?? [];

                if (($atributos['color_codigo'] ?? null) !== $colorCodigo) {
                    continue;
                }
            }

            return $configuracion;
        }

        return null;
    }

    /**
     * Busca directamente el producto recomendado.
     */
    public function buscarProductoRecomendado(
        string $configuradorCodigo,
        string $uso,
        ?string $tipo = null,
        ?float $largo = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $colorCodigo = null 
    ): ?CatalogoProducto {
        $configuracion = $this->buscarRecomendado(
            configuradorCodigo: $configuradorCodigo,
            uso: $uso,
            tipo: $tipo,
            largo: $largo,
            ancho: $ancho,
            alto: $alto,
            colorCodigo: $colorCodigo
        );

        return $configuracion?->getProducto();
    }

    /**
     * Busca una mampara recomendada.
     *
     * Devuelve siempre un array con esta forma:
     *
     * [
     *     'frontal' => CatalogoProducto,
     *     'lateral' => CatalogoProducto|null,
     * ]
     *
     * Para frontal normal:
     * - frontal: producto encontrado
     * - lateral: null
     *
     * Para angular:
     * - frontal: producto frontal compatible
     * - lateral: lateral fijo compatible
     */
    public function buscarMamparaRecomendada(
        string $tipoMampara,
        ?float $anchoFrontal = null,
        ?float $anchoLateral = null
    ): ?array {
        if ($tipoMampara === 'sin_mampara') {
            return null;
        }

        if (in_array($tipoMampara, ['angular', 'angular_dobe'], true)) {
            return $this->buscarMamparaAngularConLateral(
                tipoMampara: $tipoMampara,
                anchoFrontal: $anchoFrontal,
                anchoLateral: $anchoLateral
            );
        }

        $producto = $this->buscarProductoRecomendado(
            configuradorCodigo: 'ducha',
            uso: 'mampara',
            tipo: $tipoMampara,
            largo: null,
            ancho: $anchoFrontal,
            colorCodigo: '16' 
        );

        if (!$producto) {
            return null;
        }

        return [
            'frontal' => $producto,
            'lateral' => null,
        ];
    }

    /**
     * Busca una angular completa:
     * frontal compatible + lateral fijo compatible.
     */
    private function buscarMamparaAngularConLateral(
        string $tipoMampara,
        ?float $anchoFrontal = null,
        ?float $anchoLateral = null
    ): ?array {


        $configuracionFrontal = $this->buscarConfiguracionMamparaAngular(
            tipoMampara: $tipoMampara,
            anchoFrontal: $anchoFrontal
        );

        if (!$configuracionFrontal) {
            return null;
        }

        $atributosFrontal = $configuracionFrontal->getCondiciones() ?? [];

        if (($atributosFrontal['permite_lateral'] ?? false) !== true)
            {
            return null;
        }

        $configuracionLateral = $this->buscarConfiguracionLateralCompatible(
            configuracionFrontal: $configuracionFrontal,
            anchoLateral: $anchoLateral
        );


        if (!$configuracionLateral) {
            return null;
        }


        return [
            'frontal' => $configuracionFrontal->getProducto(),
            'lateral' => $configuracionLateral->getProducto(),
        ];
    }

    /**
     * Para una angular, busca primero una mampara frontal compatible.
     *
     * angular:
     * - puede partir de frontal_fijo_corredera
     * - o frontal_doble_corredera
     *
     * angular_doble:
     * - solo parte de frontal_doble_corredera
     */
    private function buscarConfiguracionMamparaAngular(
        string $tipoMampara,
        ?float $anchoFrontal = null
    ): ?CatalogoProductoConfiguracion {
        $tiposBase = match ($tipoMampara) {
            'angular' => [
                'frontal_fijo_corredera',
                'frontal_doble_corredera',
            ],
            'angular_doble' => [
                'frontal_doble_corredera',
            ],
            default => [],
        };

        if (!$tiposBase) {
            return null;
        }

        $qb = $this->configuracionRepository->createQueryBuilder('c')
            ->join('c.producto', 'p')
            ->addSelect('p')
            ->where('c.configuradorCodigo = :configuradorCodigo')
            ->andWhere('c.uso = :uso')
            ->andWhere('c.activo = true')
            ->andWhere('p.activo = true')
            ->andWhere('p.visiblePresupuesto = true')
            ->andWhere('c.tipo IN (:tiposBase)')
            ->setParameter('configuradorCodigo', 'ducha')
            ->setParameter('uso', 'mampara')
            ->setParameter('tiposBase', $tiposBase);

        if ($anchoFrontal !== null) {
            $qb
                ->andWhere('(c.anchoMin IS NULL OR c.anchoMin <= :anchoFrontal)')
                ->andWhere('(c.anchoMax IS NULL OR c.anchoMax >= :anchoFrontal)')
                ->setParameter('anchoFrontal', $anchoFrontal);
        }

        $configuraciones = $qb
            ->orderBy('c.recomendado', 'DESC')
            ->addOrderBy('c.prioridad', 'ASC')
            ->addOrderBy('p.precioVenta', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($configuraciones as $configuracion) {
            $atributos = $configuracion->getCondiciones() ?? [];

            if (($atributos['permite_lateral'] ?? false) === true
            && ($atributos['color_codigo'] ?? null) === '16')
            {
                return $configuracion;
            }
        }

        return null;
    }

    /**
     * Busca un lateral fijo compatible con la frontal.
     *
     * Importante:
     * - uso = lateral_mampara
     * - tipo = lateral_fijo
     * - ancho = ancho del plato
     */
    private function buscarConfiguracionLateralCompatible(
        CatalogoProductoConfiguracion $configuracionFrontal,
        ?float $anchoLateral = null
    ): ?CatalogoProductoConfiguracion {


        $atributosFrontal = $configuracionFrontal->getCondiciones() ?? [];

        $qb = $this->configuracionRepository->createQueryBuilder('c')
            ->join('c.producto', 'p')
            ->addSelect('p')
            ->where('c.configuradorCodigo = :configuradorCodigo')
            ->andWhere('c.uso = :uso')
            ->andWhere('c.tipo = :tipo')
            ->andWhere('c.activo = true')
            ->andWhere('p.activo = true')
            ->andWhere('p.visiblePresupuesto = true')
            ->setParameter('configuradorCodigo', 'ducha')
            ->setParameter('uso', 'lateral_mampara')
            ->setParameter('tipo', 'lateral_fijo_standard');


        if ($anchoLateral !== null) {
            $qb
                ->andWhere('(c.anchoMin IS NULL OR c.anchoMin <= :anchoLateral)')
                ->andWhere('(c.anchoMax IS NULL OR c.anchoMax >= :anchoLateral)')
                ->setParameter('anchoLateral', $anchoLateral);
        }


        $configuraciones = $qb
            ->orderBy('c.recomendado', 'DESC')
            ->addOrderBy('c.prioridad', 'ASC')
            ->addOrderBy('p.precioVenta', 'ASC')
            ->getQuery()
            ->getResult();


        foreach ($configuraciones as $configuracionLateral) {
            $atributosLateral = $configuracionLateral->getCondiciones() ?? [];

            if ($this->lateralCompatibleConFrontal($atributosFrontal, $atributosLateral)) {
                return $configuracionLateral;
            }
        }

        return null;
    }

    /**
     * Comprueba que el lateral pertenece al mismo modelo/acabado que la frontal.
     */
    private function lateralCompatibleConFrontal(
        array $atributosFrontal,
        array $atributosLateral
    ): bool {
        $referenciaFrontal = $atributosFrontal['referencia'] ?? null;

        if (!$referenciaFrontal) {
            return false;
        }

        $compatibleCon = $atributosLateral['compatible_con'] ?? [];

        if (!is_array($compatibleCon)) {
            return false;
        }

        if (!in_array($referenciaFrontal, $compatibleCon, true)) {
            return false;
        }

        if (
            isset($atributosFrontal['perfil'], $atributosLateral['perfil'])
            && $atributosFrontal['perfil'] !== $atributosLateral['perfil']
        ) {
            return false;
        }

        if (
            isset($atributosFrontal['vidrio'], $atributosLateral['vidrio'])
            && $atributosFrontal['vidrio'] !== $atributosLateral['vidrio']
        ) {
            return false;
        }

        if (
            isset($atributosFrontal['color_codigo'], $atributosLateral['color_codigo'])
            && $atributosFrontal['color_codigo'] !== $atributosLateral['color_codigo']
        ) {
            return false;
        }

        return true;
    }

    /**
     * Devuelve varias opciones compatibles.
     * Esto luego te servirá para mostrar alternativas en pantalla.
     */
    public function buscarOpciones(
        string $configuradorCodigo,
        string $uso,
        ?string $tipo = null,
        ?float $largo = null,
        ?float $ancho = null,
        ?float $alto = null,
        int $limite = 10
    ): array {
        $qb = $this->configuracionRepository->createQueryBuilder('c')
            ->join('c.producto', 'p')
            ->addSelect('p')
            ->where('c.configuradorCodigo = :configuradorCodigo')
            ->andWhere('c.uso = :uso')
            ->andWhere('c.activo = true')
            ->andWhere('p.activo = true')
            ->andWhere('p.visiblePresupuesto = true')
            ->setParameter('configuradorCodigo', $configuradorCodigo)
            ->setParameter('uso', $uso);

        if ($tipo !== null && $tipo !== '') {
            $qb
                ->andWhere('(c.tipo = :tipo OR c.tipo IS NULL)')
                ->setParameter('tipo', $tipo);
        }

        if ($largo !== null) {
            $qb
                ->andWhere('(c.largoMin IS NULL OR c.largoMin <= :largo)')
                ->andWhere('(c.largoMax IS NULL OR c.largoMax >= :largo)')
                ->setParameter('largo', $largo);
        }

        if ($ancho !== null) {
            $qb
                ->andWhere('(c.anchoMin IS NULL OR c.anchoMin <= :ancho)')
                ->andWhere('(c.anchoMax IS NULL OR c.anchoMax >= :ancho)')
                ->setParameter('ancho', $ancho);
        }

        if ($alto !== null) {
            $qb
                ->andWhere('(c.altoMin IS NULL OR c.altoMin <= :alto)')
                ->andWhere('(c.altoMax IS NULL OR c.altoMax >= :alto)')
                ->setParameter('alto', $alto);
        }

        return $qb
            ->orderBy('c.recomendado', 'DESC')
            ->addOrderBy('c.prioridad', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}