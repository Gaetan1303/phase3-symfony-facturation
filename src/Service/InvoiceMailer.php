<?php

namespace App\Service;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class InvoiceMailer
{
    private string $projectDir;
    private string $defaultFrom;

    public function __construct(
        private MailerInterface $mailer,
        private InvoiceManager $invoiceManager,
        private EntityManagerInterface $em,
        ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
        $this->projectDir = $params->get('kernel.project_dir');
        $this->defaultFrom = $params->has('app.mailer_from') ? $params->get('app.mailer_from') : 'no-reply@example.com';
    }

    /**
     * Send the invoice PDF to the client email.
     * Returns true on success, false otherwise.
     */
    public function sendInvoice(Invoice $invoice): bool
    {
        $client = $invoice->getClient();
        $email = $client?->getEmail();
        if (!$email) {
            $this->logger->warning('InvoiceMailer: missing client email for invoice ' . $invoice->getNumber());
            return false;
        }

        // Ensure PDF exists: either use stored path or generate one
        $path = $invoice->getPdfPath();
        if (!$path || !file_exists($this->projectDir . DIRECTORY_SEPARATOR . $path)) {
            $generated = $this->invoiceManager->generatePdf($invoice);
            if (!$generated) {
                $this->logger->error('InvoiceMailer: failed to generate PDF for invoice ' . $invoice->getNumber());
                return false;
            }
            $path = $generated;
            $this->em->wrapInTransaction(function () use ($invoice, $path) {
                $invoice->setPdfPath($path);
                $this->em->persist($invoice);
            });
        }

        $fullPath = $this->projectDir . DIRECTORY_SEPARATOR . $path;
        if (!file_exists($fullPath)) {
            $this->logger->error('InvoiceMailer: pdf file not found at ' . $fullPath);
            return false;
        }

        try {
            $this->logger->info('InvoiceMailer: debug transport', [
                'mailer_class' => \get_class($this->mailer),
                'env_MAILER_DSN' => $_ENV['MAILER_DSN'] ?? getenv('MAILER_DSN'),
            ]);
            $emailMessage = (new TemplatedEmail())
                ->from(new Address($this->defaultFrom))
                ->to(new Address($email))
                ->subject(sprintf('Votre facture %s', $invoice->getNumber()))
                ->htmlTemplate('emails/invoice.html.twig')
                ->context(['invoice' => $invoice])
                ->attachFromPath($fullPath, $invoice->getNumber() . '.pdf', 'application/pdf');

            $this->mailer->send($emailMessage);
            return true;
        } catch (RfcComplianceException $e) {
            $this->logger->error('InvoiceMailer: invalid email address ' . $email . ' - ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('InvoiceMailer: failed to send invoice ' . $invoice->getNumber() . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a payment reminder email with optional custom message and IBAN.
     */
    public function sendReminder(Invoice $invoice, string $message = ''): bool
    {
        $client = $invoice->getClient();
        $email = $client?->getEmail();
        if (!$email) {
            $this->logger->warning('InvoiceMailer: missing client email for reminder ' . $invoice->getNumber());
            return false;
        }

        // ensure PDF exists
        $path = $invoice->getPdfPath();
        if (!$path || !file_exists($this->projectDir . DIRECTORY_SEPARATOR . $path)) {
            $generated = $this->invoiceManager->generatePdf($invoice);
            if (!$generated) {
                $this->logger->error('InvoiceMailer: failed to generate PDF for reminder ' . $invoice->getNumber());
                return false;
            }
            $path = $generated;
            $this->em->wrapInTransaction(function () use ($invoice, $path) {
                $invoice->setPdfPath($path);
                $this->em->persist($invoice);
            });
        }

        $fullPath = $this->projectDir . DIRECTORY_SEPARATOR . $path;
        if (!file_exists($fullPath)) {
            $this->logger->error('InvoiceMailer: pdf file not found at ' . $fullPath);
            return false;
        }

        try {
            $owner = $invoice->getUser();
            $iban = $owner?->getIban() ?? '';
            $emailMessage = (new TemplatedEmail())
                ->from(new Address($this->defaultFrom))
                ->to(new Address($email))
                ->subject(sprintf('Relance : facture %s', $invoice->getNumber()))
                ->htmlTemplate('emails/reminder.html.twig')
                ->context(['invoice' => $invoice, 'message' => $message, 'iban' => $iban])
                ->attachFromPath($fullPath, $invoice->getNumber() . '.pdf', 'application/pdf');

            $this->mailer->send($emailMessage);
            return true;
        } catch (RfcComplianceException $e) {
            $this->logger->error('InvoiceMailer: invalid email address ' . $email . ' - ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('InvoiceMailer: failed to send reminder ' . $invoice->getNumber() . ' - ' . $e->getMessage());
            return false;
        }
    }
}
