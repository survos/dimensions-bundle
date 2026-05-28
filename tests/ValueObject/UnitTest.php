<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use Survos\ShapeContracts\Unit;

final class UnitTest extends TestCase
{
    public function testToMillimeters(): void
    {
        self::assertSame(1.0, Unit::MM->toMillimeters());
        self::assertSame(10.0, Unit::CM->toMillimeters());
        self::assertSame(1000.0, Unit::M->toMillimeters());
        self::assertSame(25.4, Unit::IN->toMillimeters());
        self::assertSame(304.8, Unit::FT->toMillimeters());
    }

    public function testAliasParsing(): void
    {
        self::assertSame(Unit::CM, Unit::fromAlias('centimeter'));
        self::assertSame(Unit::IN, Unit::fromAlias('inches'));
    }
}
