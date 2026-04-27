<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Survos\DimensionsBundle\Parser\DimensionParser;
use Survos\DimensionsBundle\ValueObject\Unit;

final class DimensionParserTest extends TestCase
{
    private DimensionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DimensionParser('mm');
    }

    /** @dataProvider singleDimensionProvider */
    public function testParseDimension(string $input, int $expectedMm): void
    {
        $dim = $this->parser->parseDimension($input);
        $this->assertSame($expectedMm, $dim->millimeters, "Parsing '{$input}'");
    }

    public static function singleDimensionProvider(): array
    {
        return [
            'bare mm'              => ['216',       216],
            'inches decimal'       => ['8.5 in',    216],
            'inches uppercase'     => ['8.5 IN',    216],
            'cm decimal'           => ['21.6 cm',   216],
            'compound feet+inches' => ['4ft 2in',  1270],
            'one foot'             => ['1ft 0in',   305],
            'zero'                 => ['0',           0],
            'one meter'            => ['1 m',       1000],
        ];
    }

    /** @dataProvider compositeDimensionProvider */
    public function testParseDimensions(string $input, int $w, int $h, ?int $d): void
    {
        $dims = $this->parser->parseDimensions($input);
        $this->assertSame($w, $dims->widthMm,  "Width of '{$input}'");
        $this->assertSame($h, $dims->heightMm, "Height of '{$input}'");
        $this->assertSame($d, $dims->depthMm,  "Depth of '{$input}'");
    }

    public static function compositeDimensionProvider(): array
    {
        return [
            'unicode × with in'       => ['8.5 × 11 in',       216, 279, null],
            'ascii x with cm'         => ['21.6 x 27.9 cm',    216, 279, null],
            'uppercase X with cm'     => ['21.6 X 27.9 cm',    216, 279, null],
            'three dims in inches'    => ['10 × 12 × 3 in',    254, 305,   76],
            'three dims in cm'        => ['10 × 20 × 5 cm',    100, 200,   50],
            'bare mm values'          => ['210 × 297',          210, 297, null],
        ];
    }

    public function testParseDimensionsThrowsOnSingleValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parseDimensions('216');
    }

    public function testParseDimensionsThrowsOnFourValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parseDimensions('1 × 2 × 3 × 4 cm');
    }

    public function testDefaultUnitOverride(): void
    {
        $parserCm = new DimensionParser('cm');
        $dim = $parserCm->parseDimension('21.6');
        $this->assertSame(216, $dim->millimeters);
    }

    public function testParseDimensionWithExplicitDefault(): void
    {
        $dim = $this->parser->parseDimension('8.5', Unit::IN);
        $this->assertSame(216, $dim->millimeters);
    }
}
