<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'inscription et d'authentification (niveau kernel, sans client HTTP).
 *
 * Pourquoi ce format de test ?
 * - Les tests sont écrits en `KernelTestCase` pour démarrer le kernel Symfony
 *   et accéder aux services (doctrine, password hasher) sans effectuer de requêtes HTTP.
 * - Cela évite les problèmes de cycle de vie du kernel liés à l'utilisation combinée
 *   de `bootKernel()` et `createClient()` dans le même test (LogicException rencontrée).
 * - Pour la rapidité et l'isolation, la base de données utilisée est SQLite en mémoire
 *   (configurée dans `setUpBeforeClass`) et le schéma est créé dynamiquement via `SchemaTool`.
 *
 * Ce fichier teste principalement :
 * - la persistance d'un utilisateur (inscription) ;
 * - la validité du hachage/matching du mot de passe (authentification logique).
 */
class RegistrationLoginTest extends KernelTestCase
{
    private $entityManager;

    public static function setUpBeforeClass(): void
    {
        // Forcer l'utilisation d'une base SQLite en mémoire pour isolation/rapidité
        putenv('DATABASE_URL=sqlite:///:memory:');
        $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
        $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
    }

    protected function setUp(): void
    {
        // Démarre le kernel et récupère l'EntityManager via le container
        self::ensureKernelShutdown();
        static::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();

        // Création dynamique du schéma en mémoire à partir des metadata
        // Ceci garantit que les tests n'ont pas besoin d'un migrate/fixture externe.
        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($this->entityManager);
            $schemaTool->createSchema($metaData);
        }
    }

    protected function tearDown(): void
    {
        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
        parent::tearDown();
    }

    public function testRegisterCreatesUser(): void
    {
        // Récupère le service de hachage de mot de passe pour simuler l'inscription
        $container = static::getContainer();
        $passwordHasher = $container->get('security.user_password_hasher');

        // Construction d'un utilisateur comme si le formulaire l'avait produit
        $user = new User();
        $user->setEmail('testuser@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setCompanyName('TestCo');
        $user->setIban('FR7612345678901234567890123');

        // Hachage du mot de passe via le service (comme le ferait le controller)
        $hashed = $passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashed);

        // Persistance et vérification en base
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $saved = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'testuser@example.com']);
        $this->assertNotNull($saved, 'L\utilisateur doit être enregistré en base');
        $this->assertSame('Test', $saved->getFirstName());
    }

    public function testLoginAuthenticatesUser(): void
    {
        // Prépare un utilisateur en base avec mot de passe haché
        $container = static::getContainer();
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = new User();
        $user->setEmail('loginuser@example.com');
        $user->setFirstName('Login');
        $user->setLastName('User');
        $user->setCompanyName('LoginCo');
        $user->setIban('FR7612345678901234567890123');
        $hashed = $passwordHasher->hashPassword($user, 'mypassword');
        $user->setPassword($hashed);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Vérifie la logique d'authentification côté serveur (matching du mot de passe)
        // Remarque : ce test ne simule pas une requête HTTP, il vérifie uniquement le
        // comportement du hasher et la validité du mot de passe stocké.
        $this->assertTrue($passwordHasher->isPasswordValid($user, 'mypassword'));
    }
}
