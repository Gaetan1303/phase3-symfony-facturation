<?php

namespace App\Tests\Service;

use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Service\DashboardService;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DashboardServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        // Use a temporary SQLite file so schema persists across requests in tests
        $dbFile = sys_get_temp_dir().'/MimineTest.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }
        $dsn = 'sqlite:///' . $dbFile;
        putenv('DATABASE_URL='.$dsn);
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    }

    public function testMonthlyAggregationByPaidAt()
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        // create schema for SQLite test DB
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metaData);
        }

        // create a test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('test');
        $em->persist($user);

        // create two paid invoices in April
        $inv1 = new Invoice();
        $inv1->setUser($user);
        $inv1->setStatus(InvoiceStatus::PAID);
        $inv1->setAmount(12.5);
        $inv1->setPaidAt(new \DateTime('2026-04-05'));
        $em->persist($inv1);

        $inv2 = new Invoice();
        $inv2->setUser($user);
        $inv2->setStatus(InvoiceStatus::PAID);
        $inv2->setAmount(23.5);
        $inv2->setPaidAt(new \DateTime('2026-04-10'));
        $em->persist($inv2);

        $em->flush();

        // dump raw invoices for debugging portability
        $conn = $em->getConnection();
        $rows = $conn->fetchAllAssociative('SELECT id, paid_at, total_ttc, user_id, status FROM invoice');
        file_put_contents(sys_get_temp_dir().'/dashboard_invoices.json', json_encode($rows));

        $svc = $container->get(DashboardService::class);
        $months = $svc->getMonthlyRevenueForUser($user, 2026);

        // April is index 3 (0-based)
        $this->assertEquals(0.0, $months[0]);
        $this->assertEquals(0.0, $months[1]);
        $this->assertEquals(0.0, $months[2]);
        $this->assertEquals(36.0, $months[3]);
    }

    protected function tearDown(): void
    {
        // remove temporary sqlite file
        $dbFile = sys_get_temp_dir().'/MimineTest.sqlite';
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        parent::tearDown();
    }
}
