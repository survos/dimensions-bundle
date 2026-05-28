<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use Survos\ShapeContracts\Length;
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\ShapeContracts\Unit;

final class DimensionsTest extends TestCase
{
    public function testRejectsNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dimensions(-1, 100);
    }

    public function testNullAxesAllowed(): void
    {
        $d = new Dimensions(210, 297);
        $this->assertNull($d->depthMm);
        $this->assertTrue($d->is2D);
    }

    public function testEmptyDimensions(): void
    {
        $d = new Dimensions();
        $this->assertTrue($d->isEmpty);
        $this->assertSame('', $d->format());
    }

    public function testPropertyHookAxes(): void
    {
        $d = new Dimensions(216, 279, null);
        $this->assertInstanceOf(Length::class, $d->width);
        $this->assertSame(216, $d->width->millimeters);
        $this->assertSame(279, $d->height->millimeters);
        $this->assertNull($d->depth);
    }

    public function testArea(): void
    {
        $d = new Dimensions(216, 279);
        $this->assertSame(216 * 279, $d->area);
    }

    public function testAreaNullWhenAxisMissing(): void
    {
        $d = new Dimensions(216);
        $this->assertNull($d->area);
    }

    public function testVolume(): void
    {
        $d = new Dimensions(100, 200, 50);
        $this->assertSame(100 * 200 * 50, $d->volume);
    }

    public function testVolumeNullWhenAxisMissing(): void
    {
        $d = new Dimensions(100, 200);
        $this->assertNull($d->volume);
    }

    public function testIsFlat(): void
    {
        $this->assertTrue((new Dimensions(216, 279))->isFlat());
        $this->assertTrue((new Dimensions(216, 279, 3))->isFlat());
        $this->assertFalse((new Dimensions(216, 279, 10))->isFlat());
    }

    public function testFormatCm(): void
    {
        $d = new Dimensions(216, 279);
        $this->assertSame('21.6 × 27.9 cm', $d->format(Unit::CM));
    }

    public function testFormatInches(): void
    {
        $d = new Dimensions(216, 279);
        $str = $d->format(Unit::IN, 1);
        $this->assertStringContainsString('in', $str);
        $this->assertStringContainsString('×', $str);
    }

    public function testToStringIsCmDefault(): void
    {
        $d = new Dimensions(210, 297);
        $this->assertSame('21.0 × 29.7 cm', (string) $d);
    }

    public function testFormatIsLocaleIndependent(): void
    {
        $d = new Dimensions(216, 279);
        // Must use '.' not ','
        $this->assertStringContainsString('21.6', $d->format(Unit::CM));
    }

    /** Letter, Legal, A4 paper sanity checks */
    public function testPaperSizes(): void
    {
        $letter = new Dimensions(216, 279);
        $this->assertSame('21.6 × 27.9 cm', $letter->format(Unit::CM));

        $legal = new Dimensions(216, 356);
        $this->assertSame('21.6 × 35.6 cm', $legal->format(Unit::CM));

        $a4 = new Dimensions(210, 297);
        $this->assertSame('21.0 × 29.7 cm', $a4->format(Unit::CM));
    }
}
