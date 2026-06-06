<?php

namespace App\Controller;

use App\Entity\StockMovimiento;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\StockMovimientoRepository;
use App\Repository\ProductosRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;


#[Route('/admin/stock')]
class StockController extends AbstractController

{
    public function __construct(
        private readonly StockMovimientoRepository $stockMovimientoRepository,
        private readonly ProductosRepository $productosRepository,
        private EntityManagerInterface $em

    ) {
    }

    #[Route('', name: 'stock_index')]
    public function index(
        StockMovimientoRepository $stockMovimientoRepository,
        ProductosRepository $productosRepository
    ): Response {
        return $this->render('stock/index.html.twig', [
            'stock' => $stockMovimientoRepository->findResumenStock(),
            'productos' => $productosRepository->findBy([], ['descripcion_Pd' => 'ASC']),
        ]);
    }

    #[Route('/asociar-producto', name: 'stock_asociar_producto', methods: ['POST'])]
    public function asociarProducto(
        Request $request,
        StockMovimientoRepository $stockMovimientoRepository,
        ProductosRepository $productosRepository
    ): Response {
        if (!$this->isCsrfTokenValid('stock_asociar_producto', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $productoId = $request->request->get('productoId');
        $descripcion = $request->request->get('descripcion');
        $referenciaProveedor = $request->request->get('referenciaProveedor') ?: null;
        $facturaProveedorId = $request->request->get('facturaProveedorId') ?: null;

        $producto = null;

        if ($productoId) {
            $producto = $productosRepository->find($productoId);
        }

        $movimientos = $stockMovimientoRepository->findMovimientosParaAsociarProducto(
            $descripcion,
            $referenciaProveedor,
            $facturaProveedorId
        );

        foreach ($movimientos as $movimiento) {
            $movimiento->setProducto($producto);
        }

        $this->em->flush();

        $this->addFlash('success', 'Producto asociado al stock correctamente.');

        return $this->redirectToRoute('stock_index');
    }    
}