<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use Survos\DimensionsBundle\ValueObject\Unit;

final class UnitTest extends TestCase
{
    public function testToMillimeters(): void
    {
        $this->assertSame(1.0, Unit::MM->toMillimeters());
        $this->assertSame(10.0, Unit::CM->toMillimeters());
        $this->assertSame(1000.0, Unit::M->toMillimeters());
        $this->assertSame(25.4, Unit::IN->toMillimeters());
        $this->assertSame(304.8, Unit::FT->toMillimeters());
    }

    public function testSymbol(): void
    {
        $this->assertSame('mm', Unit::MM->symbol());
        $this->assertSame('cm', Unit::CM->symbol());
        $this->assertSame('in', Unit::IN->symbol());
        $this->assertSame('ft', Unit::FT->symbol());
    }

    public function testFromString(): void
    {
        $this->assertSame(Unit::CM, Unit::from('cm'));
        $this->assertSame(Unit::IN, Unit::from('in'));
    }
}
