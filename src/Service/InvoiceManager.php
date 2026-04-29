<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Processor\ProcessorInterface;

class InvoiceManager
{
    public function __construct(private EntityManagerInterface $em, private InvoiceNumberGenerator $generator, private GotenbergPdfInterface $gotenbergPdf)
    {
    }

    /**
     * Generate PDF for an invoice using Gotenberg and store it under var/invoices.
     * Returns the relative path (e.g. 'var/invoices/FACT-...pdf') or null on failure.
     */
    public function generatePdf(Invoice $invoice): ?string
    {
        $projectDir = dirname(__DIR__, 2);
        $outDir = $projectDir . '/var/invoices';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $filename = $invoice->getNumber() . '.pdf';
        $target = $outDir . '/' . $filename;

        $builder = $this->gotenbergPdf->html()
            ->content('invoice/pdf.html.twig', ['invoice' => $invoice])
            ->fileName($filename);

        $processor = new class($target) implements ProcessorInterface {
            private string $target;

            public function __construct(string $target)
            {
                $this->target = $target;
            }

            public function __invoke(string|null $fileName): \Generator
            {
                $fh = fopen($this->target, 'wb');
                try {
                    while (true) {
                        $chunk = yield;
                        if ($chunk === null) {
                            continue;
                        }
                        fwrite($fh, $chunk->getContent());
                    }
                } finally {
                    fclose($fh);
                }

                return $this->target;
            }
        };

        $result = $builder->processor($processor)->generate()->process();
        if (is_string($result)) {
            return 'var/invoices/' . $filename;
        }

        return null;
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
                // try generate PDF and store path (non-blocking)
                try {
                    $path = $this->generatePdf($invoice);
                    if ($path) {
                        $invoice->setPdfPath($path);
                    }
                } catch (\Throwable $e) {
                    // ignore for now
                }
            } else {
                $invoice->setStatus(InvoiceStatus::DRAFT);
            }

            $this->em->persist($invoice);
        });
    }
}
