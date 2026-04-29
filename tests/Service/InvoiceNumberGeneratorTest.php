<?php

namespace App\Tests\Service;

use App\Service\InvoiceNumberGenerator;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    public function testGenerateForReturnsExpectedFormat()
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);

        // set up query to return count 4 (meaning next number should be 5)
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(4);

        $qb->expects($this->any())->method('select')->willReturnSelf();
        $qb->expects($this->any())->method('from')->willReturnSelf();
        $qb->expects($this->any())->method('where')->willReturnSelf();
        $qb->expects($this->any())->method('andWhere')->willReturnSelf();
        $qb->expects($this->any())->method('setParameter')->willReturnSelf();
        $qb->expects($this->any())->method('getQuery')->willReturn($query);

        $em->expects($this->any())->method('createQueryBuilder')->willReturn($qb);

        $generator = new InvoiceNumberGenerator($em);

        $date = new \DateTime('2026-04-28');
        $number = $generator->generateFor($date);

        // Expect pattern FACT-YYYYMMDD-5
        $this->assertMatchesRegularExpression('/^FACT-20260428-5$/', $number);
    }
}
