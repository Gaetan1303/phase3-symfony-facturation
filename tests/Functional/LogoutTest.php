<?php

namespace App\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fonctionnel pour la déconnexion.
 *
 * Objectif : vérifier que la route `/logout` existe et redirige vers la cible configurée
 * dans `security.yaml` (ici `/`). Ce test utilise `WebTestCase` car il exécute
 * une requête HTTP via le client Symfony.
 */
class LogoutTest extends WebTestCase
{
    public function testLogoutRedirectsToTarget(): void
    {
        // Utiliser le même fichier SQLite `MimineTest.sqlite` pour isolation locale
        $dbFile = sys_get_temp_dir().'/MimineTest.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;

        $client = static::createClient();

        // Pour déclencher proprement la logique de logout du firewall,
        // on authentifie d'abord un utilisateur via `loginUser()`.
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get('security.user_password_hasher');

        // Création dynamique du schéma en mémoire
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metaData);
        }
        $user = new \App\Entity\User();
        $user->setEmail('logoutuser@example.com');
        $user->setFirstName('Logout');
        $user->setLastName('User');
        $user->setCompanyName('LogoutCo');
        $user->setIban('FR7612345678901234567890123');
        $user->setPassword($passwordHasher->hashPassword($user, 'secret'));

        $em->persist($user);
        $em->flush();

        // Authentifie l'utilisateur côté client (pratique de WebTestCase)
        $client->loginUser($user);

        // Maintenant la requête de logout doit être interceptée et rediriger
        $client->request('GET', '/logout');

        // Le firewall devrait renvoyer une redirection (vers la target configurée).
        $this->assertTrue($client->getResponse()->isRedirect(), 'La route /logout doit rediriger');
    }
}
