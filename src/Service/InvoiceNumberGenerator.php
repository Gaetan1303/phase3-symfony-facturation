<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Invoice;

class InvoiceNumberGenerator
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Generate FACT-YYYYMMDD-N where N is count in month + 1
     */
    public function generateFor(
        \DateTimeInterface $date = null
    ): string {
        $date = $date ?: new \DateTime();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        $start = new \DateTime("{$year}-{$month}-01 00:00:00");
        $end = (clone $start)->modify('first day of next month');

        $qb = $this->em->createQueryBuilder();
        $qb->select('count(i.id)')
            ->from(Invoice::class, 'i')
            ->where('i.createdAt >= :start')
            ->andWhere('i.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        $n = $count + 1;

        // Format: FACT-YYYYMMDD-N
        return sprintf('FACT-%s%s%s-%d', $year, $month, $day, $n);
    }
}
