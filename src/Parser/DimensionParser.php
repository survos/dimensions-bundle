<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Parser;

use Survos\DimensionsBundle\ValueObject\Dimension;
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\DimensionsBundle\ValueObject\Unit;

final class DimensionParser
{
    private const UNIT_PATTERN = 'mm|cm|m|in|ft';

    public function __construct(private readonly string $defaultUnit = 'mm') {}

    /**
     * Parse a single dimension string.
     *
     * Examples:
     *   "8.5 in"    → Dimension(216)
     *   "4ft 2in"   → Dimension(1270)
     *   "21.6 cm"   → Dimension(216)
     *   "216"       → Dimension(216)  (assumes defaultUnit)
     */
    public function parseDimension(string $input, ?Unit $defaultUnit = null): Dimension
    {
        $defaultUnit ??= Unit::from($this->defaultUnit);
        $input = trim($input);

        // Compound imperial: "4ft 2in", "1ft 6in"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*ft\s+(\d+(?:\.\d+)?)\s*in$/i', $input, $m)) {
            return new Dimension((int) round((float) $m[1] * 304.8 + (float) $m[2] * 25.4));
        }

        // Value + unit: "8.5 in", "21.6 cm", "216 mm"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(' . self::UNIT_PATTERN . ')\s*$/i', $input, $m)) {
            return Dimension::from((float) $m[1], Unit::from(strtolower($m[2])));
        }

        // Bare number — uses default unit
        if (preg_match('/^(\d+(?:\.\d+)?)\s*$/', $input, $m)) {
            return Dimension::from((float) $m[1], $defaultUnit);
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse dimension: "%s"', $input));
    }

    /**
     * Parse a composite dimension string.
     *
     * Examples:
     *   "8.5 × 11 in"          → Dimensions(216, 279, null)
     *   "21.6 x 27.9 cm"       → Dimensions(216, 279, null)
     *   "10 × 12 × 3 in"       → Dimensions(254, 305, 76)
     *   "4ft 2in × 3ft"        → Dimensions(1270, 914, null)
     */
    public function parseDimensions(string $input, ?Unit $defaultUnit = null): Dimensions
    {
        $defaultUnit ??= Unit::from($this->defaultUnit);
        $input = trim($input);

        // Split on ×, x, X (with optional surrounding whitespace)
        $parts = preg_split('/\s*[×xX]\s*/u', $input);

        if (count($parts) < 2 || count($parts) > 3) {
            throw new \InvalidArgumentException(
                sprintf('Expected 2–3 dimensions separated by × in: "%s"', $input)
            );
        }

        // Extract trailing unit from the last part — it applies to all unit-less parts
        $lastPart   = trim((string) end($parts));
        $sharedUnit = $defaultUnit;

        if (preg_match('/^(.*?)\s*(' . self::UNIT_PATTERN . ')\s*$/i', $lastPart, $m)) {
            $sharedUnit                   = Unit::from(strtolower($m[2]));
            $parts[count($parts) - 1]     = trim($m[1]);
        }

        $mms = array_map(
            fn (string $part) => $this->parsePartToMm(trim($part), $sharedUnit),
            $parts
        );

        return new Dimensions($mms[0], $mms[1] ?? null, $mms[2] ?? null);
    }

    private function parsePartToMm(string $part, Unit $fallback): int
    {
        // Compound imperial within a part: "4ft 2in"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*ft\s+(\d+(?:\.\d+)?)\s*in$/i', $part, $m)) {
            return (int) round((float) $m[1] * 304.8 + (float) $m[2] * 25.4);
        }

        // Value + own unit
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(' . self::UNIT_PATTERN . ')\s*$/i', $part, $m)) {
            return (int) round((float) $m[1] * Unit::from(strtolower($m[2]))->toMillimeters());
        }

        // Bare number — uses shared/fallback unit
        if (preg_match('/^(\d+(?:\.\d+)?)\s*$/', $part, $m)) {
            return (int) round((float) $m[1] * $fallback->toMillimeters());
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse dimension part: "%s"', $part));
    }
}
