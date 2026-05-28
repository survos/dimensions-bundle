<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Service;

use Survos\ShapeContracts\Length;
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\ShapeContracts\Shape;
use Survos\ShapeContracts\Unit;

final class DimensionFormatter
{
    public function __construct(
        private readonly string $defaultDisplayUnit = 'cm',
        private readonly int $defaultPrecision = 1,
        private readonly bool $showBothUnits = false,
        private readonly ?DimensionPreferenceInterface $preference = null,
    ) {}

    public function formatDimensions(Dimensions|Shape $dimensions, ?string $unit = null, ?int $precision = null): string
    {
        if ($dimensions instanceof Shape) {
            $dimensions = Dimensions::fromShape($dimensions);
        }

        return $dimensions->format(
            Unit::fromAlias($unit ?? $this->resolveUnit()),
            $precision ?? $this->defaultPrecision,
        );
    }

    public function formatBoth(Dimensions|Shape $dimensions, ?int $precision = null): string
    {
        if ($dimensions instanceof Shape) {
            $dimensions = Dimensions::fromShape($dimensions);
        }

        $precision ??= $this->defaultPrecision;
        $primary   = $this->resolveUnit();
        $secondary = ($primary === 'cm') ? 'in' : 'cm';

        $primaryStr   = $dimensions->format(Unit::fromAlias($primary), $precision);
        $secondaryStr = $dimensions->format(Unit::fromAlias($secondary), $precision);

        return "{$primaryStr} ({$secondaryStr})";
    }

    public function formatDimension(Length $dimension, ?string $unit = null, ?int $precision = null): string
    {
        $unitObj   = Unit::fromAlias($unit ?? $this->resolveUnit());
        $precision = $precision ?? $this->defaultPrecision;

        return number_format($dimension->to($unitObj), $precision, '.', '') . ' ' . $unitObj->symbol();
    }

    /** Returns null when width or height is missing. */
    public function formatArea(Dimensions|Shape $dimensions, string $unit = 'cm', ?int $precision = null): ?string
    {
        if ($dimensions instanceof Shape) {
            $dimensions = Dimensions::fromShape($dimensions);
        }

        if ($dimensions->area === null) {
            return null;
        }
        $precision = $precision ?? $this->defaultPrecision;
        $factor    = Unit::fromAlias($unit)->toMillimeters();
        $area      = $dimensions->area / ($factor ** 2);

        return number_format($area, $precision, '.', '') . ' ' . $unit . '²';
    }

    /** Returns null when any axis is missing. */
    public function formatVolume(Dimensions|Shape $dimensions, string $unit = 'cm', ?int $precision = null): ?string
    {
        if ($dimensions instanceof Shape) {
            $dimensions = Dimensions::fromShape($dimensions);
        }

        if ($dimensions->volume === null) {
            return null;
        }
        $precision = $precision ?? $this->defaultPrecision;
        $factor    = Unit::fromAlias($unit)->toMillimeters();
        $volume    = $dimensions->volume / ($factor ** 3);

        return number_format($volume, $precision, '.', '') . ' ' . $unit . '³';
    }

    private function resolveUnit(): string
    {
        return $this->preference?->getPreferredDisplayUnit() ?? $this->defaultDisplayUnit;
    }
}
