<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Parser;

use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\ShapeContracts\Length;
use Survos\ShapeContracts\Shape;
use Survos\ShapeContracts\Unit;

final class DimensionParser
{
    private const UNIT_PATTERN = 'mm|cm|m|in|ft';

    public function __construct(private readonly string $defaultUnit = 'mm') {}

    public function parseDimension(string $input, Unit|string|null $defaultUnit = null): Length
    {
        $defaultUnit = $defaultUnit === null ? Unit::fromAlias($this->defaultUnit) : (is_string($defaultUnit) ? Unit::fromAlias($defaultUnit) : $defaultUnit);
        $input = trim($input);

        if (preg_match('/^(\d+(?:\.\d+)?)\s*ft\s+(\d+(?:\.\d+)?)\s*in$/i', $input, $m)) {
            return new Length((int) round((float) $m[1] * 304.8 + (float) $m[2] * 25.4));
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(' . self::UNIT_PATTERN . ')\s*$/i', $input, $m)) {
            return Length::from((float) $m[1], Unit::fromAlias($m[2]));
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*$/', $input, $m)) {
            return Length::from((float) $m[1], $defaultUnit);
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse dimension: "%s"', $input));
    }

    public function parseShape(string $input, Unit|string|null $defaultUnit = null): Shape
    {
        $defaultUnit = $defaultUnit === null ? Unit::fromAlias($this->defaultUnit) : (is_string($defaultUnit) ? Unit::fromAlias($defaultUnit) : $defaultUnit);
        $input = trim($input);
        $parts = preg_split('/\s*[×xX]\s*/u', $input);

        if (count($parts) < 2 || count($parts) > 3) {
            throw new \InvalidArgumentException(sprintf('Expected 2-3 dimensions separated by × in: "%s"', $input));
        }

        $lastPart = trim((string) end($parts));
        $sharedUnit = $defaultUnit;
        if (preg_match('/^(.*?)\s*(' . self::UNIT_PATTERN . ')\s*$/i', $lastPart, $m)) {
            $sharedUnit = Unit::fromAlias($m[2]);
            $parts[count($parts) - 1] = trim($m[1]);
        }

        $mms = array_map(fn (string $part): int => $this->parsePartToMm(trim($part), $sharedUnit), $parts);

        return Shape::fromMillimeters(
            widthMm: $mms[0],
            heightMm: $mms[1] ?? null,
            depthMm: $mms[2] ?? null,
            source: $input,
        );
    }

    public function parseDimensions(string $input, Unit|string|null $defaultUnit = null): Dimensions
    {
        return Dimensions::fromShape($this->parseShape($input, $defaultUnit));
    }

    private function parsePartToMm(string $part, Unit $fallback): int
    {
        if (preg_match('/^(\d+(?:\.\d+)?)\s*ft\s+(\d+(?:\.\d+)?)\s*in$/i', $part, $m)) {
            return (int) round((float) $m[1] * 304.8 + (float) $m[2] * 25.4);
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(' . self::UNIT_PATTERN . ')\s*$/i', $part, $m)) {
            return (int) round((float) $m[1] * Unit::fromAlias($m[2])->toMillimeters());
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*$/', $part, $m)) {
            return (int) round((float) $m[1] * $fallback->toMillimeters());
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse dimension part: "%s"', $part));
    }
}
