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

    /**
     * Retourne les totaux mensuels (12 éléments) des factures payées pour l'utilisateur et l'année donnée.
     * Les montants sont en float (totalTtc).
     */
    private function isSqlite(): bool
    {
        return $this->em->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
    }

    public function getMonthlyRevenueForUser(User $user, int $year): array
    {
        $result = array_fill(0, 12, 0.0);
        try {
            $conn = $this->em->getConnection();
            if ($this->isSqlite()) {
                $monthExpr = "CAST(strftime('%m', paid_at) AS INTEGER)";
            } else {
                $monthExpr = 'EXTRACT(MONTH FROM paid_at)';
            }

            $sql = sprintf(
                'SELECT %1$s AS m, COALESCE(SUM(total_ttc), 0) AS total
                 FROM invoice
                 WHERE user_id = :uid
                   AND status = :status
                   AND paid_at >= :start
                   AND paid_at < :end
                 GROUP BY %1$s',
                $monthExpr
            );

            $start = (new \DateTimeImmutable(sprintf('%d-01-01', $year)))->format('Y-m-d H:i:s');
            $end   = (new \DateTimeImmutable(sprintf('%d-01-01', $year + 1)))->format('Y-m-d H:i:s');

            $rows = $conn->executeQuery($sql, [
                'uid'    => $user->getId(),
                'status' => \App\Enum\InvoiceStatus::PAID->value,
                'start'  => $start,
                'end'    => $end,
            ])->fetchAllAssociative();

            foreach ($rows as $r) {
                $m = (int) $r['m'];
                if ($m >= 1 && $m <= 12) {
                    $result[$m - 1] = round((float) $r['total'], 2);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            // rethrow in dev to surface SQL errors
            if ($this->em->getConnection()->getDatabasePlatform() !== null && isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'test') {
                throw $e;
            }

            return array_fill(0, 12, 0.0);
        }
    }

    /**
     * Retourne un tableau associatif year => total pour les factures payées de l'utilisateur.
     */
    public function getYearlyRevenueForUser(User $user): array
    {
        $years = [];
        try {
            $conn = $this->em->getConnection();
            if ($this->isSqlite()) {
                $yearExpr = "CAST(strftime('%Y', paid_at) AS INTEGER)";
            } else {
                $yearExpr = 'EXTRACT(YEAR FROM paid_at)';
            }

            $sql = sprintf(
                'SELECT %1$s AS y, COALESCE(SUM(total_ttc), 0) AS total
                 FROM invoice
                 WHERE user_id = :uid
                   AND status = :status
                   AND paid_at IS NOT NULL
                 GROUP BY %1$s
                 ORDER BY %1$s ASC',
                $yearExpr
            );

            $rows = $conn->executeQuery($sql, [
                'uid'    => $user->getId(),
                'status' => \App\Enum\InvoiceStatus::PAID->value,
            ])->fetchAllAssociative();

            foreach ($rows as $r) {
                $years[(int) $r['y']] = (float) $r['total'];
            }

            return $years;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
