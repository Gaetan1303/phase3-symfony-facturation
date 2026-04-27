<?php

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $summary = $this->dashboardService->getSummaryForUser($user);

        return $this->render('dashboard/index.html.twig', ['summary' => $summary]);
    }
}
