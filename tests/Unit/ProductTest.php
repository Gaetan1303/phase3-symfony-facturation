<?php

namespace App\Tests\Unit;

use App\Entity\Product;
use App\Enum\Unit;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $p = new Product();
        $p->setName('Stylo');
        $p->setDescription('Stylo bleu');
        $p->setPrice('12.34');
        $p->setUnit(Unit::PIECE);

        $this->assertSame('Stylo', $p->getName());
        $this->assertSame('Stylo bleu', $p->getDescription());
        $this->assertSame('12.34', $p->getPrice());
        $this->assertSame(Unit::PIECE, $p->getUnit());
    }
}
