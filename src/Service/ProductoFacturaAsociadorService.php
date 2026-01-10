<?php
namespace App\Service;

use App\Entity\Detallecesta;
use App\Entity\Productos;
use Doctrine\ORM\EntityManagerInterface;
use App\Dto\AnalisisProductoFactura;

class ProductoFacturaAsociadorService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Devuelve un array de posibles coincidencias de detalles de cesta con este producto facturado.
     *
     * @return Detallecesta[]|array
     */
    public function buscarCoincidenciasDeProducto(Productos $producto, int $diasRecientes = 30): array
    {
        // 1. Normalizamos la descripción del producto facturado
        $descripcion = strtolower(trim($producto->getDescripcionPd()));

        // 2. Fecha límite para considerar presupuestos recientes
        $fechaLimite = new \DateTime("-{$diasRecientes} days");

        // 3. Creamos el QueryBuilder
        $qb = $this->em->createQueryBuilder();

        $qb->select('d')
            ->from(Detallecesta::class, 'd')
            ->join('d.cestaDc', 'c')
            ->join('d.productoDc', 'p')
            ->where('c.estadoCs IN (:estados)')
            ->andWhere('c.fechaCs >= :fechaReciente')
            ->setParameter('estados', [2, 3]) // 2 = Finalizada, 3 = Aceptado
            ->setParameter('fechaReciente', $fechaLimite)
            ->setMaxResults(20);
        $qb->andWhere(
            $qb->expr()->orX(
            'd.costeActualizadoPorFactura = false',
            'd.costeActualizadoPorFactura IS NULL'
        )
        );
        // 4. Dividimos la descripción en palabras
        $palabras = preg_split('/\s+/', $descripcion);

        $palabras = array_filter($palabras, function ($palabra) {
            $palabra = trim($palabra);

            return strlen($palabra) >= 3   // 🔑 mínimo 3 letras
                && !in_array($palabra, ['en', 'de', 'con', 'sin', 'para']); // stop words
        });        

        // 5. Añadimos un LIKE por cada palabra relevante

        $orX = $qb->expr()->orX();

        foreach ($palabras as $i => $palabra) {
            $paramName = "p$i";
            $orX->add($qb->expr()->like('LOWER(p.descripcion_Pd)', ":$paramName"));
            $qb->setParameter($paramName, '%' . $palabra . '%');
        }

        $qb->andWhere($orX);


        // 6. Ejecutamos la consulta
        return $qb->getQuery()->getResult();
    }



    public function analizarProductosDeFactura(array $productos, int $diasRecientes = 30): array
    {
        $resultado = [];

        foreach ($productos as $productoFactura) {
            $coincidencias = $this->buscarCoincidenciasDeProducto($productoFactura, $diasRecientes);

            foreach ($coincidencias as $detalle) {
                $costeNuevo = $productoFactura->getPrecioPd() * 1.262;
                $resultado[] = new AnalisisProductoFactura($productoFactura, $detalle, $costeNuevo);
            }
        }

        return $resultado;
    }


}

?>