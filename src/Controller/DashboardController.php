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
    public function index(
        \Symfony\Component\HttpFoundation\Request $request
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $summary = $this->dashboardService->getSummaryForUser($user);

        // period: 'monthly' or 'yearly'
        $period = $request->query->get('period', 'monthly');
        $year = (int) $request->query->get('year', (int) date('Y'));

        $chart = ['labels' => [], 'data' => []];
        $availableYears = array_keys($this->dashboardService->getYearlyRevenueForUser($user));
        rsort($availableYears);

        if ($period === 'yearly') {
            $yearly = $this->dashboardService->getYearlyRevenueForUser($user);
            $chart['labels'] = array_map('strval', array_keys($yearly));
            $chart['data'] = array_values($yearly);
        } else {
            // monthly
            $monthly = $this->dashboardService->getMonthlyRevenueForUser($user, $year);
            $chart['labels'] = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            $chart['data'] = $monthly;
        }

        $response = $this->render('dashboard/index.html.twig', ['summary' => $summary, 'chart' => $chart, 'period' => $period, 'year' => $year, 'availableYears' => $availableYears]);

        // Ensure the dashboard page is not cached by browsers during development
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
