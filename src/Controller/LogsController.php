<?php

namespace App\Controller;

use App\Entity\Logs;
use App\Form\LogsType;
use App\Repository\LogsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/logs")
 */
class LogsController extends AbstractController
{
    /**
     * @Route("/", name="logs_index", methods={"GET"})
     */
    public function index(LogsRepository $logsRepository): Response
    {
        return $this->render('logs/index.html.twig', [
            'logs' => $logsRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="logs_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $log = new Logs();
        $form = $this->createForm(LogsType::class, $log);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('logs_index');
        }

        return $this->render('logs/new.html.twig', [
            'log' => $log,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="logs_show", methods={"GET"})
     */
    public function show(Logs $log): Response
    {
        return $this->render('logs/show.html.twig', [
            'log' => $log,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="logs_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Logs $log): Response
    {
        $form = $this->createForm(LogsType::class, $log);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('logs_index');
        }

        return $this->render('logs/edit.html.twig', [
            'log' => $log,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="logs_delete", methods={"POST"})
     */
    public function delete(Request $request, Logs $log): Response
    {
        if ($this->isCsrfTokenValid('delete'.$log->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($log);
            $entityManager->flush();
        }

        return $this->redirectToRoute('logs_index');
    }
}
