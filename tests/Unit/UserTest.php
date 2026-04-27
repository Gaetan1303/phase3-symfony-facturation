<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité User.
 *
 * Objectif : vérifier les getters/setters afin de s'assurer que l'entité
 * stocke et retourne correctement les valeurs attendues.
 *
 * Remarque : on teste également que le rôle `ROLE_USER` est présent par défaut
 * (implémentation de l'entité qui garantit un rôle minimum).
 */
class UserTest extends TestCase
{
    public function testUserGettersAndSetters(): void
    {
        $user = new User();
        $user->setEmail('alice@example.com');
        $user->setFirstName('Alice');
        $user->setLastName('Dupont');
        $user->setCompanyName('ACME');
        $user->setIban('FR7630006000011234567890189');
        $user->setSiret('12345678900010');
        $user->setPassword('hashedpassword');
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertSame('alice@example.com', $user->getEmail());
        $this->assertSame('Alice', $user->getFirstName());
        $this->assertSame('Dupont', $user->getLastName());
        $this->assertSame('ACME', $user->getCompanyName());
        $this->assertSame('FR7630006000011234567890189', $user->getIban());
        $this->assertSame('12345678900010', $user->getSiret());
        $this->assertSame('hashedpassword', $user->getPassword());
        // Vérifie que les rôles personnalisés sont présents
        $this->assertContains('ROLE_ADMIN', $user->getRoles());

        // L'entité doit toujours inclure au minimum le rôle `ROLE_USER`
        $this->assertContains('ROLE_USER', $user->getRoles());
    }
}
