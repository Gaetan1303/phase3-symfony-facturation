<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Product;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductCrudTest extends WebTestCase
{
    protected function setUp(): void
    {
        $dbFile = sys_get_temp_dir().'/MimineTest_Product.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    }

    public function testCreateListDeleteProduct(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metaData);
        }

        // Create a user and log in
        $user = new User();
        $user->setEmail('prodtest@example.com');
        $user->setFirstName('Prod');
        $user->setLastName('Tester');
        $user->setCompanyName('TestCo');
        $user->setIban('FR7630006000011234567890189');

        $passwordHasher = $container->get('security.user_password_hasher');
        $hashed = $passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashed);

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        // Create product
        $crawler = $client->request('GET', '/products/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['product[name]'] = 'ProduitTest';
        $form['product[description]'] = 'Description test';
        $form['product[price]'] = '9.99';
        $form['product[unit]'] = 'piece';

        $client->submit($form);
        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        // Verify product persisted in database
        $saved = $em->getRepository(Product::class)->findOneBy(['name' => 'ProduitTest']);
        if (null === $saved) {
            file_put_contents(sys_get_temp_dir().'/product_create_response.html', $client->getResponse()->getContent());
        }
        $this->assertNotNull($saved, 'Le produit doit être présent en base (debug: '.sys_get_temp_dir().'/product_create_response.html)');

        // Delete the created product by finding the row and submitting its form
        $crawler = $client->request('GET', '/products');
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
            $crawler = $client->getCrawler();
        }
        // Dump HTML for debugging if the assertion fails
        file_put_contents(sys_get_temp_dir().'/product_list_response.html', $client->getResponse()->getContent());
        $row = $crawler->filterXPath("//tr[.//td[contains(., 'ProduitTest')]]");
        $this->assertGreaterThan(0, $row->count());

        $deleteForm = $row->filter('form')->first()->form();
        $client->submit($deleteForm);
        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        $this->assertSelectorTextNotContains('table', 'ProduitTest');
    }

    protected function tearDown(): void
    {
        $dbFile = sys_get_temp_dir().'/MimineTest_Product.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        parent::tearDown();
    }
}
