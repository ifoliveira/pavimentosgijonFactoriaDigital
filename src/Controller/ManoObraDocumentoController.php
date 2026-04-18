<?php

namespace App\Controller;

use App\Entity\ManoObra;
use App\Form\ManoObraType;
use App\Form\PresupuestosManoObraType;
use App\Repository\ManoObraRepository;
use App\Repository\PresupuestosRepository;
use App\Repository\DocumentoRepository;
use App\Form\DocumentoManoObraType;
use App\Repository\TipoManoObraRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/documento/{docId}/mano-obra', name: 'documento_mano_obra_')]
class ManoObraDocumentoController extends AbstractController
{


    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        int $docId,
        DocumentoRepository $documentoRepository,
        TipoManoObraRepository $tipoManoObraRepository
    ): Response {
        $documento = $documentoRepository->find($docId);
            $categorias = $tipoManoObraRepository->findAll(); // ← añadir

        if (!$documento) {
            throw $this->createNotFoundException("Documento $docId no encontrado");
        }

        $manoObra = new ManoObra();
        $documento->addManoObra($manoObra);

        $form = $this->createForm(DocumentoManoObraType::class, $documento);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($manoObra);
            $this->em->flush();

            // Después de guardar, formulario limpio para la siguiente fila
            $manoObraNueva = new ManoObra();
            $documento->addManoObra($manoObraNueva);
            $form = $this->createForm(DocumentoManoObraType::class, $documento);

            return $this->render('documento/manoObra.html.twig', [
                'documento' => $documento,
                'mano_obra' => $manoObraNueva,
                'categorias' => $categorias,
                'form'      => $form->createView(),
            ]);
        }

        return $this->render('documento/manoObra.html.twig', [
            'documento' => $documento,
            'mano_obra' => $manoObra,
            'categorias' => $categorias,
            'form'      => $form->createView(),
        ]);
    }
}