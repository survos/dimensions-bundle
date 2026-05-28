<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use Survos\ShapeContracts\Length;
use Survos\ShapeContracts\Unit;

final class DimensionTest extends TestCase
{
    public function testContractLengthRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Length(-1);
    }

    public function testContractLengthConversions(): void
    {
        self::assertSame(216, Length::from(8.5, Unit::IN)->millimeters);
        self::assertSame(210, Length::from(21, Unit::CM)->millimeters);
        self::assertSame(1000, Length::from(1, Unit::M)->millimeters);
        self::assertEqualsWithDelta(8.5, Length::from(8.5, Unit::IN)->to(Unit::IN), 0.01);
    }

    public function testToString(): void
    {
        self::assertSame('216 mm', (string) new Length(216));
    }
}
