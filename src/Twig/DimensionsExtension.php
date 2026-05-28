<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Twig;

use Survos\DimensionsBundle\Service\DimensionFormatter;
use Survos\ShapeContracts\Length;
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\ShapeContracts\Shape;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class DimensionsExtension extends AbstractExtension implements GlobalsInterface
{
    /** Iconify keys used by templates/dimensions.html.twig — override via Twig context if needed. */
    public const DEFAULT_ICONS = [
        'height' => 'tabler:arrow-autofit-height',
        'width'  => 'tabler:arrow-autofit-width',
        'length' => 'tabler:ruler',
        'depth'  => 'tabler:box',
        'radius' => 'tabler:circle-dashed',
        'weight' => 'tabler:weight',
    ];

    public function __construct(private readonly DimensionFormatter $formatter) {}

    public function getGlobals(): array
    {
        return ['dimIcons' => self::DEFAULT_ICONS];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('dim_render', [$this, 'render'], ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('dim',        [$this, 'dim']),
            new TwigFilter('dim_both',   [$this, 'dimBoth']),
            new TwigFilter('dim_value',  [$this, 'dimValue']),
            new TwigFilter('dim_area',   [$this, 'dimArea']),
            new TwigFilter('dim_volume', [$this, 'dimVolume']),
        ];
    }

    public function render(Environment $env, iterable|null $dimensions = [], iterable|null $weight = [], ?string $raw = null): string
    {
        return $env->render('@SurvosDimensions/dimensions.html.twig', [
            'dimensions' => $dimensions ?? [],
            'weight'     => $weight ?? [],
            'raw'        => $raw,
        ]);
    }

    /**
     * {{ item.dimensions|dim }}            → "21.6 × 27.9 cm"
     * {{ item.dimensions|dim('in', 2) }}   → "8.50 × 11.00 in"
     */
    public function dim(Dimensions|Shape|null $dimensions, string $unit = 'cm', int $precision = 1): string
    {
        if ($dimensions === null || $dimensions->isEmpty) {
            return '';
        }
        return $this->formatter->formatDimensions($dimensions, $unit, $precision);
    }

    /**
     * {{ item.dimensions|dim_both }}   → "21.6 × 27.9 cm (8.5 × 11.0 in)"
     */
    public function dimBoth(Dimensions|Shape|null $dimensions, int $precision = 1): string
    {
        if ($dimensions === null || $dimensions->isEmpty) {
            return '';
        }
        return $this->formatter->formatBoth($dimensions, $precision);
    }

    /**
     * {{ item.thickness|dim_value }}         → "0.3 mm"
     * {{ item.thickness|dim_value('mm', 0) }} → "0"
     */
    public function dimValue(Length|null $dimension, string $unit = 'mm', int $precision = 1): string
    {
        if ($dimension === null) {
            return '';
        }
        return $this->formatter->formatDimension($dimension, $unit, $precision);
    }

    /**
     * {{ item.dimensions|dim_area('cm') }}   → "602.6 cm²"
     */
    public function dimArea(Dimensions|Shape|null $dimensions, string $unit = 'cm', int $precision = 1): string
    {
        if ($dimensions === null) {
            return '';
        }
        return $this->formatter->formatArea($dimensions, $unit, $precision) ?? '';
    }

    /**
     * {{ item.dimensions|dim_volume('cm') }}  → "1807.7 cm³"
     */
    public function dimVolume(Dimensions|Shape|null $dimensions, string $unit = 'cm', int $precision = 1): string
    {
        if ($dimensions === null) {
            return '';
        }
        return $this->formatter->formatVolume($dimensions, $unit, $precision) ?? '';
    }
}
