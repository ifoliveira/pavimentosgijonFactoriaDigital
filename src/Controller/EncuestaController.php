<?php

namespace App\Controller;

use App\Entity\Encuesta;
use App\Form\EncuestaType;
use App\Repository\EncuestaRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/encuesta')]
class EncuestaController extends AbstractController
{

    #[Route('', name: 'app_encuesta_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EncuestaRepository $encuestaRepository): Response
    {
        $encuestum = new Encuesta();
        $form = $this->createForm(EncuestaType::class, $encuestum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encuestum->setFecha(New DateTime());

            $encuestum->setCliente($request->get('firm'));

            $encuestaRepository->add($encuestum, true);

            return $this->redirectToRoute('homepage', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('encuesta/new.html.twig', [
            'encuestum' => $encuestum,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_encuesta_show', methods: ['GET'])]
    public function show(Encuesta $encuestum): Response
    {
        return $this->render('encuesta/show.html.twig', [
            'encuestum' => $encuestum,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_encuesta_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Encuesta $encuestum, EncuestaRepository $encuestaRepository): Response
    {
        $form = $this->createForm(EncuestaType::class, $encuestum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encuestaRepository->add($encuestum, true);

            return $this->redirectToRoute('app_encuesta_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('encuesta/edit.html.twig', [
            'encuestum' => $encuestum,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_encuesta_delete', methods: ['POST'])]
    public function delete(Request $request, Encuesta $encuestum, EncuestaRepository $encuestaRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$encuestum->getId(), $request->request->get('_token'))) {
            $encuestaRepository->remove($encuestum, true);
        }

        return $this->redirectToRoute('app_encuesta_index', [], Response::HTTP_SEE_OTHER);
    }
}
