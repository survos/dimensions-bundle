<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Survos\DimensionsBundle\Parser\DimensionParser;
use Survos\DimensionsBundle\Service\DimensionsNormalizer;

final class DimensionsNormalizerTest extends TestCase
{
    private DimensionsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DimensionsNormalizer(new DimensionParser('mm'));
    }

    public function testNullSourceReturnsAllNull(): void
    {
        $this->assertSame(
            ['dimensions' => null, 'dimensionsRaw' => null, 'weight' => null],
            $this->normalizer->normalize(null),
        );
    }

    public function testEmptyStringReturnsAllNull(): void
    {
        $this->assertSame(
            ['dimensions' => null, 'dimensionsRaw' => null, 'weight' => null],
            $this->normalizer->normalize(''),
        );
    }

    public function testEmptyArrayReturnsAllNull(): void
    {
        $this->assertSame(
            ['dimensions' => null, 'dimensionsRaw' => null, 'weight' => null],
            $this->normalizer->normalize([]),
        );
    }

    public function testStructuredArrayPassesThrough(): void
    {
        $source = [
            ['height' => 21.6, 'width' => 27.9, 'units' => 'cm'],
        ];
        $result = $this->normalizer->normalize($source);
        $this->assertSame(
            [['height' => 21.6, 'width' => 27.9, 'units' => 'cm']],
            $result['dimensions'],
        );
        $this->assertNull($result['dimensionsRaw']);
        $this->assertNull($result['weight']);
    }

    public function testStructuredArrayCanonicalizesUnits(): void
    {
        $source = [['height' => 10, 'units' => 'centimeter']];
        $result = $this->normalizer->normalize($source);
        $this->assertSame('cm', $result['dimensions'][0]['units']);
    }

    public function testSmkTypedArrayFoldsIntoSingleRecord(): void
    {
        $source = [
            ['type' => 'height', 'value' => '21.6', 'unit' => 'centimeter'],
            ['type' => 'width',  'value' => '27.9', 'unit' => 'centimeter'],
        ];
        $result = $this->normalizer->normalize($source);

        $this->assertCount(1, $result['dimensions']);
        $this->assertSame(21.6, $result['dimensions'][0]['height']);
        $this->assertSame(27.9, $result['dimensions'][0]['width']);
        $this->assertSame('cm',  $result['dimensions'][0]['units']);
        $this->assertNull($result['weight']);
    }

    public function testSmkTypedArrayGroupsByPartType(): void
    {
        $source = [
            ['type' => 'height', 'value' => '50', 'unit' => 'centimeter', 'partType' => 'sight'],
            ['type' => 'width',  'value' => '40', 'unit' => 'centimeter', 'partType' => 'sight'],
            ['type' => 'height', 'value' => '60', 'unit' => 'centimeter', 'partType' => 'framed'],
            ['type' => 'width',  'value' => '48', 'unit' => 'centimeter', 'partType' => 'framed'],
        ];
        $result = $this->normalizer->normalize($source);

        $this->assertCount(2, $result['dimensions']);
        $byName = array_column($result['dimensions'], null, 'name');
        $this->assertSame(50.0, $byName['sight']['height']);
        $this->assertSame(60.0, $byName['framed']['height']);
    }

    public function testSmkTypedArrayExtractsWeight(): void
    {
        $source = [
            ['type' => 'height', 'value' => '10', 'unit' => 'centimeter'],
            ['type' => 'weight', 'value' => '450', 'unit' => 'gram'],
        ];
        $result = $this->normalizer->normalize($source);

        $this->assertCount(1, $result['dimensions']);
        $this->assertSame(10.0, $result['dimensions'][0]['height']);
        $this->assertCount(1, $result['weight']);
        $this->assertSame(['amount' => 450.0, 'units' => 'g'], $result['weight'][0]);
    }

    public function testWaltersParentheticalMetricExtraction(): void
    {
        $source = 'H: 3 5/8 × Diam: 1 1/4 in. (9.2 × 3.2 cm)';
        $result = $this->normalizer->normalize($source);

        $this->assertNotNull($result['dimensions']);
        $this->assertCount(1, $result['dimensions']);
        $this->assertSame('cm', $result['dimensions'][0]['units']);
        $this->assertSame(9.2,  $result['dimensions'][0]['width']);
        $this->assertSame(3.2,  $result['dimensions'][0]['height']);
        $this->assertSame($source, $result['dimensionsRaw']);
    }

    public function testPlainShapeStringWithUnit(): void
    {
        $result = $this->normalizer->normalize('21.6 × 27.9 cm');
        $this->assertNotNull($result['dimensions']);
        $this->assertSame('cm', $result['dimensions'][0]['units']);
        $this->assertSame(21.6, $result['dimensions'][0]['width']);
        $this->assertSame(27.9, $result['dimensions'][0]['height']);
        $this->assertNull($result['dimensionsRaw']);
    }

    public function testThreeDimensionalShape(): void
    {
        $result = $this->normalizer->normalize('10 × 20 × 30 cm');
        $record = $result['dimensions'][0];
        $this->assertSame(10.0, $record['width']);
        $this->assertSame(20.0, $record['height']);
        $this->assertSame(30.0, $record['depth']);
    }

    public function testUnparseableStringFallsBackToRaw(): void
    {
        $source = 'roughly the size of a breadbox';
        $result = $this->normalizer->normalize($source);

        $this->assertNull($result['dimensions']);
        $this->assertSame($source, $result['dimensionsRaw']);
        $this->assertNull($result['weight']);
    }

    public function testUnparseableArrayFallsBackToRawJson(): void
    {
        $source = [['mystery' => 'box']];
        $result = $this->normalizer->normalize($source);

        $this->assertNull($result['dimensions']);
        $this->assertNotNull($result['dimensionsRaw']);
        $this->assertStringContainsString('mystery', $result['dimensionsRaw']);
    }
}
