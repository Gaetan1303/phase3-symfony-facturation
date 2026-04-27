<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardTest extends WebTestCase
{
    protected function setUp(): void
    {
        // Ensure test DB file is unique and persisted for the client
        $dbFile = sys_get_temp_dir().'/MimineTest.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    }

    public function testDashboardShowsSummary(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create schema
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metaData);
        }

        // Create sample data
        $user = new User();
        $user->setEmail('dash@example.com');
        $user->setFirstName('Dash');
        $user->setLastName('User');
        $user->setCompanyName('DashCo');
        $user->setIban('FR7630006000011234567890189');
        $user->setPassword('hashed');

        $clientEntity = new Client();
        $clientEntity->setName('Client A');
        $product = new Product();
        $product->setName('Product A')->setPrice(10.0);

        $inv1 = new Invoice();
        $inv1->setAmount(100.00)->setUser($user)->setClient($clientEntity);
        $inv2 = new Invoice();
        $inv2->setAmount(50.00)->setUser($user)->setClient($clientEntity);

        $em->persist($user);
        $em->persist($clientEntity);
        $em->persist($product);
        $em->persist($inv1);
        $em->persist($inv2);
        $em->flush();

        // Authenticate the user in the client and request the dashboard
        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Tableau de bord');
        $this->assertSelectorTextContains('.metrics', 'Factures');
        $this->assertSelectorTextContains('.metrics', 'Chiffre d\'affaires');
    }
}
