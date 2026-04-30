<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ClientCrudTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private User $user;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        // Use a unique test sqlite DB for this test class to avoid cross-test contamination
        $this->dbFile = sys_get_temp_dir().'/MimineTest_Client_'.uniqid().'.sqlite';
        $dbFile = $this->dbFile;
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
        // Ensure previous kernel is shut down, then boot a fresh client/kernel
        static::ensureKernelShutdown();
        $client = static::createClient();
        $this->em = $client->getContainer()->get(EntityManagerInterface::class);

        // Ensure schema exists (bootstrap should have created it, but be defensive)
        $metaData = $this->em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
            try {
                $schemaTool->createSchema($metaData);
            } catch (\Doctrine\DBAL\Exception\TableExistsException | \Doctrine\ORM\Tools\ToolsException $e) {
                // Table(s) already exist in shared test DB; ignore and continue
            }
        }

        // Create a test user
        $this->user = new User();
        $this->user->setEmail('test@example.com')
            ->setPassword('password123')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setCompanyName('Test Company')
            ->setRoles(['ROLE_USER']);

        $this->em->persist($this->user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        // Clean up the test sqlite file and call parent
        try {
            $this->em->getConnection()->executeStatement('DELETE FROM client');
            $this->em->getConnection()->executeStatement('DELETE FROM users');
        } catch (\Exception $e) {
            // ignore
        }

        parent::tearDown();
        if (isset($this->dbFile) && file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    public function testClientListPage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user);

        $client->request('GET', '/clients/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mes clients', $client->getResponse()->getContent());
    }

    public function testCreateClientForm(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user);

        $client->request('GET', '/clients/new');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Ajouter un client', $client->getResponse()->getContent());
    }

    public function testCreateClient(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user);

        $client->request('GET', '/clients/new');
        $client->submitForm('Créer le client', [
            'client_type[name]' => 'ACME Corp',
            'client_type[email]' => 'contact@acme.com',
            'client_type[phone]' => '+33 1 23 45 67 89',
            'client_type[address]' => '123 Rue de la Paix',
            'client_type[siret]' => '12345678901234',
            'client_type[rib]' => 'FR1420041010050500013M02606',
        ]);

        self::assertResponseRedirects('/clients/');

        $client->followRedirect();
        self::assertStringContainsString('ACME Corp', $client->getResponse()->getContent());
        self::assertStringContainsString('Client créé avec succès', $client->getResponse()->getContent());
    }

    public function testEditClient(): void
    {
        // Create a test client
        $testClient = new Client();
        $testClient->setName('Original Name')
            ->setEmail('old@example.com')
            ->setPhone('+33 1 11 11 11 11')
            ->setAddress('Old Address')
            ->setSiret('11111111111111')
            ->setRib('FR1420041010050500013M02606')
            ->setUser($this->user);

        $this->em->persist($testClient);
        $this->em->flush();

        $client = static::createClient();
        $client->loginUser($this->user);

        $client->request('GET', '/clients/' . $testClient->getId() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Modifier le client', $client->getResponse()->getContent());
        self::assertStringContainsString('Original Name', $client->getResponse()->getContent());

        // Submit the form with updated values
        $client->submitForm('Enregistrer les modifications', [
            'client_type[name]' => 'Updated Name',
            'client_type[email]' => 'new@example.com',
        ]);

        self::assertResponseRedirects('/clients/');

        $client->followRedirect();
        self::assertStringContainsString('Updated Name', $client->getResponse()->getContent());
        self::assertStringContainsString('Client modifié avec succès', $client->getResponse()->getContent());
    }

    public function testDeleteClient(): void
    {
        // Create a test client
        $testClient = new Client();
        $testClient->setName('Client to Delete')
            ->setEmail('delete@example.com')
            ->setRib('FR1420041010050500013M02606')
            ->setUser($this->user);

        $this->em->persist($testClient);
        $this->em->flush();

        $clientId = $testClient->getId();

        $client = static::createClient();
        $client->loginUser($this->user);

        // Get the edit page to extract CSRF token
        $crawler = $client->request('GET', '/clients/' . $clientId . '/edit');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();

        // Now delete the client
        $token = $this->getCsrfTokenForRoute($client, 'delete' . $clientId);
        $client->request('POST', '/clients/' . $clientId . '/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/clients/');

        $client->followRedirect();
        self::assertStringContainsString('Client supprimé avec succès', $client->getResponse()->getContent());

        // Verify the client is deleted
        $deletedClient = $this->em->getRepository(Client::class)->find($clientId);
        self::assertNull($deletedClient);
    }

    public function testAccessDeniedForOtherUserClient(): void
    {
        // Create another user
        $otherUser = new User();
        $otherUser->setEmail('other@example.com')
            ->setPassword('password123')
            ->setFirstName('Jane')
            ->setLastName('Smith')
            ->setCompanyName('Other Company')
            ->setRoles(['ROLE_USER']);

        $this->em->persist($otherUser);

        // Create a client for the other user
        $clientOfOther = new Client();
        $clientOfOther->setName('Other User Client')
            ->setEmail('client@example.com')
            ->setRib('FR1420041010050500013M02606')
            ->setUser($otherUser);

        $this->em->persist($clientOfOther);
        $this->em->flush();

        // Try to access with the first user
        $client = static::createClient();
        $client->loginUser($this->user);

        $client->request('GET', '/clients/' . $clientOfOther->getId() . '/edit');

        self::assertResponseStatusCodeSame(403);
    }

    private function getCsrfTokenForRoute($client, string $tokenId): ?string
    {
        // For simplicity, we'll use a mock token strategy
        // In a real scenario, you'd extract it from the page or use a different approach
        return 'test-token';
    }
}
