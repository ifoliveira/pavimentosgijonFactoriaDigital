<?php

namespace App\Controller;

use App\Entity\Documento;
use App\Entity\DocumentoConfiguracion;
use App\Repository\DocumentoConfiguracionRepository;
use App\Repository\PresupuestoConfiguradorRepository;
use App\Service\PresupuestoDuchaBuilderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;



#[Route('/admin/documento/configurador')]
class PresupuestoConfiguradorController extends AbstractController
{

    #[Route('/{id}/ducha', name: 'app_documento_configurar_ducha', methods: ['GET', 'POST'])]
    public function ducha(
        Documento $documento,
        Request $request,
        PresupuestoConfiguradorRepository $configuradorRepository,
        DocumentoConfiguracionRepository $documentoConfiguracionRepository,
        PresupuestoDuchaBuilderService $duchaBuilderService,
        EntityManagerInterface $em
    ): Response {
        $configurador = $configuradorRepository->findOneBy([
            'codigo' => 'ducha',
            'activo' => true,
        ]);

        if (!$configurador) {
            throw $this->createNotFoundException('No existe el configurador de ducha.');
        }

        $configuracion = $documentoConfiguracionRepository->findOneBy([
            'documento' => $documento,
        ]);

        if (!$configuracion) {
            $configuracion = new DocumentoConfiguracion();
            $configuracion->setDocumento($documento);
            $configuracion->setConfigurador($configurador);
            $configuracion->setCodigoConfigurador($configurador->getCodigo());
        }

        if ($request->isMethod('POST')) {
            $datos = [];

            foreach ($configurador->getCampos() as $campo) {
                if (!$campo->isActivo()) {
                    continue;
                }

                $codigo = $campo->getCodigo();
                $tipoCampo = $campo->getTipoCampo();

                if ($tipoCampo === 'boolean') {
                    $datos[$codigo] = $request->request->has($codigo);
                    continue;
                }

                $valor = $request->request->get($codigo);

                if ($tipoCampo === 'number') {
                    $valor = $valor !== null && $valor !== '' ? (float) str_replace(',', '.', $valor) : null;
                }

                $datos[$codigo] = $valor;
            }

            $configuracion->setDatos($datos);

            $em->persist($configuracion);
            $em->flush();

            if ($request->request->get('accion') === 'generar') {
                $duchaBuilderService->generar($documento, $configuracion);

                $this->addFlash('success', 'Presupuesto generado correctamente desde la configuración de ducha.');

                return $this->redirectToRoute('app_documento_show', [
                    'id' => $documento->getId(),
                ]);
            }            

            $this->addFlash('success', 'Configuración de ducha guardada correctamente.');

            return $this->redirectToRoute('app_documento_configurar_ducha', [
                'id' => $documento->getId(),
            ]);
        }

        return $this->render('presupuesto_configurador/ducha.html.twig', [
            'documento' => $documento,
            'configurador' => $configurador,
            'configuracion' => $configuracion,
            'datos' => $configuracion->getDatos(),
        ]);
    }
}