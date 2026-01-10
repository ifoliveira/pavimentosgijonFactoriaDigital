<?php   
namespace App\Service;

use App\Entity\Productos;
use App\Entity\DetalleCesta;
use Doctrine\ORM\EntityManagerInterface;

class ProductoFacturaActualizadorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductoFacturaAsociadorService $asociador
    ) {}

    /**
     * Trata los productos extraídos de la factura.
     *
     * @param Productos[] $productos
     * @param string $pdfFilename
     */
    public function actualizarCostesDesdeFactura(array $productos, string $pdfFilename): array
    {
        $actualizados = [];

        foreach ($productos as $productoFactura) {

            $coincidencias = $this->asociador->buscarCoincidenciasDeProducto($productoFactura);

            foreach ($coincidencias as $detalle) {
                $costeNuevo = $productoFactura->getPrecioPd();
                $costeActual = $detalle->getCoste();

                if (abs($costeNuevo - $costeActual) > 0.01) {
                    // Guardamos trazabilidad y actualizamos el coste
                    $detalle->setCosteAnterior($costeActual);
                    $detalle->setCoste($costeNuevo);
                    $detalle->setCosteActualizadoPorFactura(true);
                    $detalle->setFacturaOrigen($pdfFilename);
                    $detalle->setFechaActualizacionCoste(new \DateTime());

                    $this->em->persist($detalle);

                    $actualizados[] = $detalle;
                }
            }
        }

        $this->em->flush();

        return $actualizados;
    }
}
