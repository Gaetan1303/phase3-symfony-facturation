<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que la configuration de session contient bien une durée de cookie
 * correspondant au timeout attendu (3600s = 1 heure).
 *
 * Pourquoi : CA3 exige une déconnexion automatique au bout d'une heure. Dans
 * Symfony, le comportement le plus simple est d'expirer le cookie de session
 * après `cookie_lifetime` secondes. Ce test lit le paramètre d'options de
 * session exposé par le container.
 */
class SessionTimeoutTest extends KernelTestCase
{
    public function testSessionCookieLifetimeIsOneHour(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $options = [];
        if ($container->hasParameter('session.storage.options')) {
            $options = $container->getParameter('session.storage.options');
        }

        $this->assertArrayHasKey('cookie_lifetime', $options, 'La configuration de session doit définir cookie_lifetime');
        $this->assertSame(3600, $options['cookie_lifetime']);
    }
}
