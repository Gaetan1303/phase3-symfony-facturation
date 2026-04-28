<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileTest extends WebTestCase
{
    protected function setUp(): void
    {
        $dbFile = sys_get_temp_dir().'/MimineTest_Profile.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    }

    public function testAccessAndEditProfile(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metaData);
        }

        // Create and persist a user
        $user = new User();
        $user->setEmail('profiletest@example.com');
        $user->setFirstName('Prof');
        $user->setLastName('Tester');
        $user->setCompanyName('OrigCo');
        $user->setIban('FR7612345678901234567890123');

        $passwordHasher = $container->get('security.user_password_hasher');
        $hashed = $passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashed);

        $em->persist($user);
        $em->flush();

        // Login
        $client->loginUser($user);

        // CA1: access profile page from menu (direct route)
        $crawler = $client->request('GET', '/profile/');
        $this->assertResponseIsSuccessful();

        // CA4: existing data should be visible in the form
        $this->assertSelectorExists('input[name="profile[companyName]"]');
        $this->assertSelectorExists('input[name="profile[iban]"]');
        $this->assertSame('OrigCo', $crawler->filter('input[name="profile[companyName]"]')->attr('value'));
        $this->assertStringContainsString('FR7612345678901234567890123', $crawler->filter('input[name="profile[iban]"]')->attr('value'));

        // CA2 & CA3: update IBAN and companyName
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['profile[companyName]'] = 'NewCo SARL';
        $form['profile[iban]'] = 'FR7611111111111111111111111';

        $client->submit($form);
        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        // Reload user from DB and assert changes persisted
        $saved = $em->getRepository(User::class)->findOneBy(['email' => 'profiletest@example.com']);
        $this->assertNotNull($saved);
        $this->assertSame('NewCo SARL', $saved->getCompanyName());
        $this->assertSame('FR7611111111111111111111111', $saved->getIban());
    }

    protected function tearDown(): void
    {
        $dbFile = sys_get_temp_dir().'/MimineTest_Profile.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        parent::tearDown();
    }
}
