<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Service\InvoiceManager;
use App\Service\InvoiceMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Mailer\MailerInterface;
use Psr\Log\NullLogger;

class InvoiceMailerTest extends TestCase
{
    public function testSendInvoiceSuccess(): void
    {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'inv_test_');
        file_put_contents($tmpFile, "PDF-CONTENT");
        $basename = basename($tmpFile);

        $client = new Client();
        $client->setName('ACME');
        $client->setEmail('client@example.test');

        $invoice = new Invoice();
        $invoice->setNumber('TEST-123');
        $invoice->setPdfPath($basename);
        $invoice->setClient($client);

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->once())->method('send');

        $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        $emMock = $this->createMock(EntityManagerInterface::class);

        $params = new ParameterBag(['kernel.project_dir' => $tmpDir]);

        $service = new InvoiceMailer($mailerMock, $invoiceManagerMock, $emMock, $params, new NullLogger());

        $result = $service->sendInvoice($invoice);
        $this->assertTrue($result);

        // cleanup
        @unlink($tmpFile);
    }

    public function testSendInvoiceMissingEmail(): void
    {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'inv_test_');
        file_put_contents($tmpFile, "PDF-CONTENT");
        $basename = basename($tmpFile);

        $invoice = new Invoice();
        $invoice->setNumber('TEST-456');
        $invoice->setPdfPath($basename);
        // client is null -> no email

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        $emMock = $this->createMock(EntityManagerInterface::class);

        $params = new ParameterBag(['kernel.project_dir' => $tmpDir]);

        $service = new InvoiceMailer($mailerMock, $invoiceManagerMock, $emMock, $params, new NullLogger());

        $result = $service->sendInvoice($invoice);
        $this->assertFalse($result);

        @unlink($tmpFile);
    }
}
