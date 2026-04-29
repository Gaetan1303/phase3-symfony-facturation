<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceManager
{
    public function __construct(private EntityManagerInterface $em, private InvoiceNumberGenerator $generator)
    {
    }

    /**
     * Persist and prepare invoice (calculate totals, set status/number) inside a transaction.
     */
    public function persistInvoice(Invoice $invoice, $user, bool $validate = false): void
    {
        $this->em->wrapInTransaction(function () use ($invoice, $user, $validate): void {
            $invoice->setUser($user);

            // ensure lines reference invoice
            foreach ($invoice->getLines() as $line) {
                $line->setInvoice($invoice);
            }

            // recalc amount on entity
            $invoice->recalculateAmount();

            if ($validate) {
                $invoice->setStatus(InvoiceStatus::PENDING_PAYMENT);
                if (empty($invoice->getNumber())) {
                    $invoice->setNumber($this->generator->generateFor(new \DateTimeImmutable()));
                }
            } else {
                $invoice->setStatus(InvoiceStatus::DRAFT);
            }

            $this->em->persist($invoice);
        });
    }
}
