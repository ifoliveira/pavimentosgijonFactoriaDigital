<?php

namespace App\Controller;

use App\MisClases\OpenAiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IAPresupuestoController extends AbstractController
{
    private $openAiService;

    public function __construct(OpenAiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

    /**
     * @Route("/generate-budget", name="generate_budget", methods={"POST"})
     */
    public function generateBudget(Request $request): Response
    {
        $description = $request->request->get('description');
        
        if (!$description) {
            return new Response('Please provide a description.', Response::HTTP_BAD_REQUEST);
        }

        $proposal = $this->openAiService->generateProposalFromDescription($description);

        if (!$proposal) {
            return new Response('Failed to generate the budget.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response($proposal);
    }

    /**
     * @Route("/budget/form", name="budget_form", methods={"GET"})
     */
    public function showForm(): Response
    {
        return $this->render('ia/iapresupuesto.html.twig');
    }

}
