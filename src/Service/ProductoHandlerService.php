<?php
namespace App\Service;

use App\Entity\Productos;
use App\Form\ProductosType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductoHandlerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FormFactoryInterface $formFactory
    ) {}

    public function crearFormularioProductos(?array $datos, Request $request)
    {
        $productos = [];

        foreach ($datos['articulos'] ?? [] as $articulo) {
            $producto = new Productos();
            $producto->setDescripcionPd((string) ($articulo['descripcion'] ?? ''));
            $cantidad = (int) ($articulo['cantidad'] ?? 1);
            $precio = round(floatval(($articulo['importe_final'] ?? 0) / max($cantidad, 1)) * 1.262, 2);
            $pvp = round($precio * 1.50, 2);

            $producto->setPrecioPd($precio);
            $producto->setPvpPd($pvp);
            $producto->setStockPd($cantidad);
            $producto->setFecAltaPd(new \DateTime());
            $producto->setObsoleto(false);

            $productos[] = $producto;
        }

        $form = $this->formFactory->createBuilder()
            ->add('productos', CollectionType::class, [
                'entry_type' => ProductosType::class,
                'entry_options' => ['label' => false],
                'data' => $productos, // ← AQUÍ la clave
            ])
            ->getForm();


        $form->handleRequest($request);

        return $form;
    }

    public function guardarProductosDesdeFormulario($productosForm): void
    {
        $productosFormsData = $productosForm->get('productos');

        // Precargar todos los productos existentes para evitar consultas por cada uno
        $existentes = $this->em->getRepository(Productos::class)->findAll();
        $mapa = [];
        foreach ($existentes as $p) {
            $mapa[strtolower(trim($p->getDescripcionPd()))] = $p;
        }

        foreach ($productosFormsData as $formItem) {
            if ($formItem->get('incluir')->getData()) {
                /** @var Productos $producto */
                $producto = $formItem->getData();
                $descKey = strtolower(trim($producto->getDescripcionPd()));

                if (isset($mapa[$descKey])) {
                    $existente = $mapa[$descKey];
                    $existente->setPrecioPd($producto->getPrecioPd());
                    $existente->setPvpPd($producto->getPvpPd());
                    $existente->setStockPd($producto->getStockPd());
                    $existente->setFecAltaPd(new \DateTime());
                } else {
                    $this->em->persist($producto);
                }
            }
        }

        $this->em->flush();
    }
}

?>