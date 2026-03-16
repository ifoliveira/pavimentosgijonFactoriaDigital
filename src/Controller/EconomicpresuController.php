<?php

namespace App\Controller;

use App\Entity\Economicpresu;
use App\Entity\Efectivo;
use App\Form\EconomicpresuType;
use App\Repository\BancoRepository;
use App\Repository\EconomicpresuRepository;
use App\Repository\TiposmovimientoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/economicpresu')]
class EconomicpresuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'app_economicpresu_index', methods: ['GET'])]
    public function index(EconomicpresuRepository $economicpresuRepository): Response
    {
        return $this->render('economicpresu/index.html.twig', [
            'economicpresus' => $economicpresuRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_economicpresu_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $economicpresu = new Economicpresu();
        $form = $this->createForm(EconomicpresuType::class, $economicpresu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($economicpresu);
            $this->em->flush();

            return $this->redirectToRoute('app_economicpresu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('economicpresu/new.html.twig', [
            'economicpresu' => $economicpresu,
            'form'          => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_economicpresu_show', methods: ['GET'])]
    public function show(Economicpresu $economicpresu): Response
    {
        return $this->render('economicpresu/show.html.twig', [
            'economicpresu' => $economicpresu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_economicpresu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Economicpresu $economicpresu): Response
    {
        $form = $this->createForm(EconomicpresuType::class, $economicpresu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('app_economicpresu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('economicpresu/edit.html.twig', [
            'economicpresu' => $economicpresu,
            'form'          => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_economicpresu_delete', methods: ['POST'])]
    public function delete(Request $request, Economicpresu $economicpresu): Response
    {
        if ($this->isCsrfTokenValid('delete' . $economicpresu->getId(), $request->request->get('_token'))) {
            $this->em->remove($economicpresu);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_economicpresu_index', [], Response::HTTP_SEE_OTHER);
    }

    // ANTES de /{id} para evitar conflicto de rutas
    #[Route('/estado/ajax', name: 'economic_estado', methods: ['GET','POST'])]
    public function estadoajax(Request $request): JsonResponse
    {
        $id     = $request->query->get('id');
        $estado = $request->query->get('estado');

        $actualizar = $this->em->getRepository(Economicpresu::class)->findOneBy(['id' => $id]);

        if (!$actualizar) {
            return new JsonResponse(['error' => 'No encontrado'], 404);
        }

        $actualizar->setEstadoEco($estado);
        $this->em->flush();

        return new JsonResponse(['id' => $id]);
    }

    #[Route('/importe/ajax', name: 'economic_importe', methods: ['GET','POST'])]
    public function importeajax(Request $request): JsonResponse
    {
        $id      = $request->query->get('id');
        $importe = $request->query->get('importe');

        $actualizar = $this->em->getRepository(Economicpresu::class)->findOneBy(['id' => $id]);

        if (!$actualizar) {
            return new JsonResponse(['error' => 'No encontrado'], 404);
        }

        $actualizar->setImporteEco($importe);
        $this->em->flush();

        return new JsonResponse(['id' => $id]);
    }

    #[Route('/pagar/ajax', name: 'economic_pagar', methods: ['GET','POST'])]
    public function pagarajax(Request $request, TiposmovimientoRepository $tiposmovimientoRepository): JsonResponse
    {
        $id      = $request->query->get('id');
        $importe = (float) $request->query->get('importe');

        $actualizar = $this->em->getRepository(Economicpresu::class)->findOneBy(['id' => $id]);

        if (!$actualizar) {
            return new JsonResponse(['error' => 'No encontrado'], 404);
        }

        $efectivo = new Efectivo();
        $efectivo->setTipoEf($tiposmovimientoRepository->findOneBy(['descripcionTm' => 'Mano de Obra']));
        $efectivo->setImporteEf($importe * -1);
        $efectivo->setFechaEf(new \DateTime());
        $efectivo->setConceptoEf($actualizar->getConceptoEco() . ' ' . $actualizar->getIdpresuEco()->getClientePe()->getDireccionCl());
        $efectivo->setPresupuestoef($actualizar->getIdpresuEco());
        $this->em->persist($efectivo);

        $restante = $actualizar->getImporteEco() - $importe;

        if ($restante <= 0) {
            $actualizar->setImporteEco($importe);
            $actualizar->setTimestamp(new \DateTime());
            $actualizar->setEstadoEco(8);
        } else {
            $actualizar->setImporteEco($restante);
            $actualizar->setTimestamp(new \DateTime());

            $economicnuevo = clone $actualizar;
            $economicnuevo->setEstadoEco(8);
            $economicnuevo->setImporteEco($importe);
            $this->em->persist($economicnuevo);
        }

        $this->em->flush();

        return new JsonResponse(['id' => $id]);
    }

    #[Route('/conciliar/{id}', name: 'conciliar_presu', methods: ['GET'])]
    public function conciliar(Economicpresu $economicpresu, BancoRepository $bancoRepository): Response
    {
        return $this->render('economicpresu/conciliar.html.twig', [
            'economicpresu' => $economicpresu,
            'bancos'        => $bancoRepository->findAll(),
        ]);
    }

    #[Route('/{id}/{idbanco}/conciliar', name: 'economicpresu_conciliar', methods: ['GET','POST'])]
    public function conciliar_banco(
        Economicpresu $economicpresu,
        BancoRepository $bancoRepository,
        int $idbanco,
        TiposmovimientoRepository $tiposmovimientoRepository
    ): Response {
        $banco = $bancoRepository->find($idbanco);

        if ($economicpresu->getImporteEco() != $banco->getImporteBn()) {
            $banconuevo = clone $banco;
            $banconuevo->setImporteBn($economicpresu->getImporteEco());
            $banconuevo->setCategoriaBn($tiposmovimientoRepository->findOneBy(['descripcionTm' => 'Mano de Obra']));
            $banconuevo->setConciliado(1);
            $banco->setImporteBn($banco->getImporteBn() - $economicpresu->getImporteEco());
            $economicpresu->setBancoEco($banconuevo);
            $this->em->persist($banconuevo);
        } else {
            $economicpresu->setBancoEco($banco);
            $banco->setCategoriaBn($tiposmovimientoRepository->findOneBy(['descripcionTm' => 'Mano de Obra']));
            $banco->setConciliado(1);
        }

        $this->em->flush();

        return $this->redirectToRoute('cestas_index');
    }
}