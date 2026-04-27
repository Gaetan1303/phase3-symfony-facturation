<?php

namespace App\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test E2E d'authentification : inscription via formulaire, puis connexion via formulaire.
 * Utilise SQLite en mémoire pour isoler la base et crée le schéma dynamiquement.
 */
class AuthE2ETest extends WebTestCase
{
    protected function setUp(): void
    {
        // Utiliser un fichier SQLite temporaire nommé `MimineTest.sqlite`
        // pour que le schéma soit partagé entre la création et les requêtes HTTP.
        $dbFile = sys_get_temp_dir().'/MimineTest.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    }

    public function testRegisterThenLoginFlow(): void
    {
        $client = static::createClient();

        // Créer le schéma en mémoire avant d'effectuer les requêtes qui persistent
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metaData);
        }

        // Aller sur la page d'inscription
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        // Remplir et soumettre le formulaire
        $form = $crawler->selectButton('Créer mon compte')->form();
        $form['registration_form[email]'] = 'e2e@example.com';
        $form['registration_form[plainPassword]'] = 'password123';
        $form['registration_form[firstName]'] = 'E2E';
        $form['registration_form[lastName]'] = 'Test';
        $form['registration_form[companyName]'] = 'E2ECo';
        $form['registration_form[iban]'] = 'FR7630006000011234567890189';

        $client->submit($form);

        // Après inscription, le controller devrait rediriger vers la page de login
        if (! $client->getResponse()->isRedirect()) {
            // Sauvegarde du HTML de réponse pour debug local
            file_put_contents(sys_get_temp_dir().'/e2e_register_response.html', $client->getResponse()->getContent());
        }
        $this->assertTrue($client->getResponse()->isRedirect(), 'Après inscription on doit être redirigé (voir '.sys_get_temp_dir().'/e2e_register_response.html)');
        $client->followRedirect();

        // Page de login affichée
        $this->assertSelectorTextContains('.login-title, .login-header, h1', 'Se connecter');

        // Soumettre le formulaire de connexion
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Se connecter')->form();
        $form['email'] = 'e2e@example.com';
        $form['password'] = 'password123';

        $client->submit($form);

        // Après connexion, attendre une redirection (vers / ou dashboard)
        $this->assertTrue($client->getResponse()->isRedirect(), 'Après connexion on doit être redirigé');
    }

    protected function tearDown(): void
    {
        // Nettoie le fichier SQLite temporaire pour ne pas polluer l'environnement local
        $dbFile = sys_get_temp_dir().'/MimineTest.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        parent::tearDown();
    }
}
