<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fournit les usecases liés au tableau de bord (résumés chiffrés, listes récentes).
 * Respecte SOLID : classe simple, responsabilité unique (préparer les données pour la vue).
 */
class DashboardService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Retourne un tableau associatif de métriques pour l'utilisateur donné.
     * ACID : les calculs sont faits en lecture seule, en requêtes transactionnelles si nécessaire.
     */
    public function getSummaryForUser(User $user): array
    {
        try {
            $invoiceRepo = $this->em->getRepository(Invoice::class);

            $qb = $invoiceRepo->createQueryBuilder('i')
                ->select('COUNT(i.id) as invoicesCount, COALESCE(SUM(i.totalTtc),0) as totalRevenue')
                ->where('i.user = :user')
                ->setParameter('user', $user);

            $result = $qb->getQuery()->getOneOrNullResult();

            $clientsCount = (int) $this->em->getRepository(\App\Entity\Client::class)
                ->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->getQuery()->getSingleScalarResult();

            $productsCount = (int) $this->em->getRepository(\App\Entity\Product::class)
                ->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->getQuery()->getSingleScalarResult();

            $recentInvoices = $invoiceRepo->createQueryBuilder('i')
                ->where('i.user = :user')
                ->setParameter('user', $user)
                ->orderBy('i.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            return [
                'invoicesCount' => (int) ($result['invoicesCount'] ?? 0),
                'totalRevenue' => (float) ($result['totalRevenue'] ?? 0.0),
                'clientsCount' => $clientsCount,
                'productsCount' => $productsCount,
                'recentInvoices' => $recentInvoices,
            ];
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException|\Doctrine\DBAL\Exception\DriverException $e) {
            // If the invoice/client/product tables are not yet created (dev setup),
            // return safe defaults to avoid a 500 error on the dashboard.
            return [
                'invoicesCount' => 0,
                'totalRevenue' => 0.0,
                'clientsCount' => 0,
                'productsCount' => 0,
                'recentInvoices' => [],
            ];
        }
    }
}
