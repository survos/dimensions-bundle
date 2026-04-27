<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Service;

use Survos\DimensionsBundle\ValueObject\Dimension;
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\DimensionsBundle\ValueObject\Unit;

final class DimensionFormatter
{
    public function __construct(
        private readonly string $defaultDisplayUnit = 'cm',
        private readonly int $defaultPrecision = 1,
        private readonly bool $showBothUnits = false,
        private readonly ?DimensionPreferenceInterface $preference = null,
    ) {}

    public function formatDimensions(Dimensions $dimensions, ?string $unit = null, ?int $precision = null): string
    {
        return $dimensions->format(
            Unit::from($unit ?? $this->resolveUnit()),
            $precision ?? $this->defaultPrecision,
        );
    }

    public function formatBoth(Dimensions $dimensions, ?int $precision = null): string
    {
        $precision ??= $this->defaultPrecision;
        $primary   = $this->resolveUnit();
        $secondary = ($primary === 'cm') ? 'in' : 'cm';

        $primaryStr   = $dimensions->format(Unit::from($primary), $precision);
        $secondaryStr = $dimensions->format(Unit::from($secondary), $precision);

        return "{$primaryStr} ({$secondaryStr})";
    }

    public function formatDimension(Dimension $dimension, ?string $unit = null, ?int $precision = null): string
    {
        $unitObj   = Unit::from($unit ?? $this->resolveUnit());
        $precision = $precision ?? $this->defaultPrecision;

        return number_format($dimension->to($unitObj), $precision, '.', '') . ' ' . $unitObj->symbol();
    }

    /** Returns null when width or height is missing. */
    public function formatArea(Dimensions $dimensions, string $unit = 'cm', ?int $precision = null): ?string
    {
        if ($dimensions->area === null) {
            return null;
        }
        $precision = $precision ?? $this->defaultPrecision;
        $factor    = Unit::from($unit)->toMillimeters();
        $area      = $dimensions->area / ($factor ** 2);

        return number_format($area, $precision, '.', '') . ' ' . $unit . '²';
    }

    /** Returns null when any axis is missing. */
    public function formatVolume(Dimensions $dimensions, string $unit = 'cm', ?int $precision = null): ?string
    {
        if ($dimensions->volume === null) {
            return null;
        }
        $precision = $precision ?? $this->defaultPrecision;
        $factor    = Unit::from($unit)->toMillimeters();
        $volume    = $dimensions->volume / ($factor ** 3);

        return number_format($volume, $precision, '.', '') . ' ' . $unit . '³';
    }

    private function resolveUnit(): string
    {
        return $this->preference?->getPreferredDisplayUnit() ?? $this->defaultDisplayUnit;
    }
}
