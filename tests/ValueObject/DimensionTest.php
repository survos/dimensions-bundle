<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use Survos\DimensionsBundle\ValueObject\Dimension;
use Survos\DimensionsBundle\ValueObject\Unit;

final class DimensionTest extends TestCase
{
    public function testConstructorRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dimension(-1);
    }

    public function testZeroIsValid(): void
    {
        $d = new Dimension(0);
        $this->assertSame(0, $d->millimeters);
    }

    /** @dataProvider roundTripProvider */
    public function testRoundTripConversions(float $value, Unit $unit, int $expectedMm): void
    {
        $dim = Dimension::from($value, $unit);
        $this->assertSame($expectedMm, $dim->millimeters);
        $this->assertEqualsWithDelta($value, $dim->to($unit), 0.01);
    }

    public static function roundTripProvider(): array
    {
        return [
            'letter width in inches' => [8.5, Unit::IN, 216],
            'letter height in inches' => [11.0, Unit::IN, 279],
            'A4 width in cm' => [21.0, Unit::CM, 210],
            'A4 height in cm' => [29.7, Unit::CM, 297],
            'one meter' => [1.0, Unit::M, 1000],
            'one foot' => [1.0, Unit::FT, 305],
            'bare mm' => [216.0, Unit::MM, 216],
        ];
    }

    public function testPropertyHooksCm(): void
    {
        $d = Dimension::fromMm(100);
        $this->assertEqualsWithDelta(10.0, $d->cm, 0.001);
    }

    public function testPropertyHooksInches(): void
    {
        $d = Dimension::fromInches(8.5);
        $this->assertEqualsWithDelta(8.5, $d->inches, 0.01);
    }

    public function testPropertyHooksMeters(): void
    {
        $d = Dimension::fromMeters(1.0);
        $this->assertEqualsWithDelta(1.0, $d->meters, 0.001);
    }

    public function testPropertyHooksFeet(): void
    {
        $d = Dimension::fromFeet(1.0);
        $this->assertEqualsWithDelta(1.0, $d->feet, 0.01);
    }

    public function testEquals(): void
    {
        $a = Dimension::fromMm(216);
        $b = Dimension::fromInches(8.504); // rounds to 216 mm
        $this->assertTrue($a->equals($b));
    }

    public function testArithmetic(): void
    {
        $a = Dimension::fromMm(100);
        $b = Dimension::fromMm(50);
        $this->assertSame(150, $a->plus($b)->millimeters);
        $this->assertSame(50, $a->minus($b)->millimeters);
    }

    public function testToString(): void
    {
        $d = Dimension::fromMm(216);
        $this->assertSame('216 mm', (string) $d);
    }

    public function testVeryLargeDimension(): void
    {
        // Space-shuttle cargo bay: ~18 m × 4.6 m
        $d = Dimension::fromMeters(18.0);
        $this->assertSame(18000, $d->millimeters);
    }
}
