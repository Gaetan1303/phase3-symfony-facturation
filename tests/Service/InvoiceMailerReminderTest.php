<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\User;
use App\Service\InvoiceManager;
use App\Service\InvoiceMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Mailer\MailerInterface;
use Psr\Log\NullLogger;

class InvoiceMailerReminderTest extends TestCase
{
    public function testSendReminderSuccess(): void
    {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'inv_rem_');
        file_put_contents($tmpFile, "PDF-CONTENT");
        $basename = basename($tmpFile);

        $client = new Client();
        $client->setName('ACME');
        $client->setEmail('client@example.test');

        $user = new User();
        $user->setIban('FR1420041010050500013M02606');

        $invoice = new Invoice();
        $invoice->setNumber('REM-123');
        $invoice->setPdfPath($basename);
        $invoice->setClient($client);
        $invoice->setUser($user);

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->once())->method('send');

        $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        $emMock = $this->createMock(EntityManagerInterface::class);

        $params = new ParameterBag(['kernel.project_dir' => $tmpDir]);

        $service = new InvoiceMailer($mailerMock, $invoiceManagerMock, $emMock, $params, new NullLogger());

        $result = $service->sendReminder($invoice, "Paiement en retard, merci de régler.");
        $this->assertTrue($result);

        @unlink($tmpFile);
    }

    public function testSendReminderMissingEmail(): void
    {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'inv_rem_');
        file_put_contents($tmpFile, "PDF-CONTENT");
        $basename = basename($tmpFile);

        $invoice = new Invoice();
        $invoice->setNumber('REM-456');
        $invoice->setPdfPath($basename);
        // client is null -> no email

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $invoiceManagerMock = $this->createMock(InvoiceManager::class);
        $emMock = $this->createMock(EntityManagerInterface::class);

        $params = new ParameterBag(['kernel.project_dir' => $tmpDir]);

        $service = new InvoiceMailer($mailerMock, $invoiceManagerMock, $emMock, $params, new NullLogger());

        $result = $service->sendReminder($invoice, "Message inutile");
        $this->assertFalse($result);

        @unlink($tmpFile);
    }
}
