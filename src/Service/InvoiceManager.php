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

                // Generate PDF via Gotenberg and save binary to var/invoices
                try {
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
                                    // ChunkInterface provides getContent()
                                    fwrite($fh, $chunk->getContent());
                                }
                            } finally {
                                fclose($fh);
                            }

                            return $this->target;
                        }
                    };

                    $resultPath = $builder->processor($processor)->generate()->process();
                    if (is_string($resultPath)) {
                        // store relative path
                        $invoice->setPdfPath('var/invoices/' . $filename);
                    }
                } catch (\Throwable $e) {
                    // Swallow errors for now but log if needed (don't break invoice persistence)
                }
            } else {
                $invoice->setStatus(InvoiceStatus::DRAFT);
            }

            $this->em->persist($invoice);
        });
    }
}
