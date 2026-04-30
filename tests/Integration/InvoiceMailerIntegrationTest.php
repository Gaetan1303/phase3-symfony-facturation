<?php

namespace App\Tests\Integration;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Service\InvoiceManager;
use App\Service\InvoiceMailer;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class InvoiceMailerIntegrationTest extends KernelTestCase
{
    public function testMailerInMemoryReceivesMessage(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $projectDir = $container->getParameter('kernel.project_dir');

        // create a temporary PDF file in project dir
        $tmpFile = tempnam($projectDir, 'inv_test_');
        file_put_contents($tmpFile, "PDF-CONTENT");
        $basename = basename($tmpFile);

        $client = new Client();
        $client->setName('ACME');
        $client->setEmail('client@example.test');

        $invoice = new Invoice();
        $invoice->setNumber('INT-123');
        $invoice->setPdfPath($basename);
        $invoice->setClient($client);

        // Prepare a simple in-memory transport for the test
        $messages = [];
        $testTransport = new class($messages) implements TransportInterface {
            public array $messages = [];
            public function __construct(array &$messages)
            {
                $this->messages = & $messages;
            }
            public function __toString(): string
            {
                return 'in-memory-test';
            }
            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): ?\Symfony\Component\Mailer\SentMessage
            {
                $this->messages[] = $message;
                return null;
            }
            public function __serialize(): array { return []; }
            public function __unserialize(array $data): void {}
        };

        $mailer = new Mailer($testTransport);

        // Get real services to pass to InvoiceMailer
        $invoiceManager = $container->get(InvoiceManager::class);
        $em = $container->get('doctrine.orm.entity_manager');
        $params = $container->get('parameter_bag');

        // Manually instantiate InvoiceMailer with our test mailer
        $invoiceMailer = new InvoiceMailer($mailer, $invoiceManager, $em, $params, new NullLogger());

        $sent = $invoiceMailer->sendInvoice($invoice);
        $this->assertTrue($sent, 'InvoiceMailer did not report success');

        $this->assertGreaterThanOrEqual(1, count($testTransport->messages), 'Expected at least one sent message in transport');

        @unlink($tmpFile);
    }
}
