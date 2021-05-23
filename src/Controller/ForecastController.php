<?php

namespace App\Controller;

use App\Entity\Forecast;
use App\Form\ForecastType;
use App\Repository\BancoRepository;
use App\Repository\DetallecestaRepository;
use App\Repository\ForecastRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/forecast")
 */
class ForecastController extends AbstractController
{
    /**
     * @Route("/", name="forecast_index", methods={"GET"})
     */
    public function index(ForecastRepository $forecastRepository, BancoRepository $bancoRepository, DetallecestaRepository $detallecestaRepository): Response
    {
        return $this->render('forecast/index.html.twig', [
            'forecasts' => $forecastRepository->findByEstadoFr("P"),
            'bancos' => $bancoRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="forecast_new", methods={"GET","POST"})
     */
    public function new(Request $request , DetallecestaRepository $detallecestaRepository): Response
    {
        $forecast = new Forecast();
        $form = $this->createForm(ForecastType::class, $forecast);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($forecast);
            $entityManager->flush();

            return $this->redirectToRoute('forecast_index');
        }

        return $this->render('forecast/new.html.twig', [
            'forecast' => $forecast,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="forecast_show", methods={"GET"})
     */
    public function show(Forecast $forecast , DetallecestaRepository $detallecestaRepository): Response

    {
        return $this->render('forecast/show.html.twig', [
            'forecast' => $forecast,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="forecast_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Forecast $forecast , DetallecestaRepository $detallecestaRepository): Response

    {
        $form = $this->createForm(ForecastType::class, $forecast);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('forecast_index');
        }

        return $this->render('forecast/edit.html.twig', [
            'forecast' => $forecast,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="forecast_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Forecast $forecast, DetallecestaRepository $detallecestaRepository): Response

    {
        if ($this->isCsrfTokenValid('delete'.$forecast->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($forecast);
            $entityManager->flush();
        }

        return $this->redirectToRoute('forecast_index');
    }

    /**
     * @Route("/{id}/{estado}/conciliar", name="forecast_conciliar", methods={"GET","POST"})
     */
    public function conciliar(Request $request, Forecast $forecast,  DetallecestaRepository $detallecestaRepository, string $estado): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $forecast->setEstadoFr($estado);
        $entityManager->flush();

        return $this->redirectToRoute('forecast_index');
    }

}
