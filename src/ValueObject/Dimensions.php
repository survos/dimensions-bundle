<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use Survos\ShapeContracts\Length;
use Survos\ShapeContracts\Shape;
use Survos\ShapeContracts\Unit;

#[ORM\Embeddable]
final class Dimensions implements \Stringable
{
    public function __construct(
        #[ORM\Column(name: 'width_mm', type: 'integer', nullable: true)]
        public readonly ?int $widthMm = null,

        #[ORM\Column(name: 'height_mm', type: 'integer', nullable: true)]
        public readonly ?int $heightMm = null,

        #[ORM\Column(name: 'depth_mm', type: 'integer', nullable: true)]
        public readonly ?int $depthMm = null,
    ) {
        foreach ([$widthMm, $heightMm, $depthMm] as $v) {
            if ($v !== null && $v < 0) {
                throw new \InvalidArgumentException('Dimensions cannot be negative.');
            }
        }
    }

    public ?Length $width { get => Length::fromMillimeters($this->widthMm); }
    public ?Length $height { get => Length::fromMillimeters($this->heightMm); }
    public ?Length $depth { get => Length::fromMillimeters($this->depthMm); }

    public ?int $area {
        get => $this->widthMm !== null && $this->heightMm !== null
            ? $this->widthMm * $this->heightMm
            : null;
    }

    public ?int $volume {
        get => $this->widthMm !== null && $this->heightMm !== null && $this->depthMm !== null
            ? $this->widthMm * $this->heightMm * $this->depthMm
            : null;
    }

    public bool $is2D { get => $this->depthMm === null; }
    public bool $isEmpty { get => $this->widthMm === null && $this->heightMm === null && $this->depthMm === null; }

    public static function fromShape(Shape $shape): self
    {
        return new self(
            widthMm: $shape->width?->millimeters,
            heightMm: $shape->height?->millimeters,
            depthMm: $shape->depth?->millimeters ?? $shape->length?->millimeters ?? $shape->thickness?->millimeters,
        );
    }

    public function toShape(?string $label = null, ?string $source = null): Shape
    {
        return Shape::fromMillimeters(
            widthMm: $this->widthMm,
            heightMm: $this->heightMm,
            depthMm: $this->depthMm,
            label: $label,
            source: $source,
        );
    }

    public function isFlat(int $thresholdMm = 5): bool
    {
        return $this->depthMm === null || $this->depthMm < $thresholdMm;
    }

    public function format(Unit|string $unit = Unit::CM, int $precision = 1): string
    {
        $unit = is_string($unit) ? Unit::fromAlias($unit) : $unit;
        $parts = [];
        foreach ([$this->widthMm, $this->heightMm, $this->depthMm] as $mm) {
            if ($mm === null) {
                continue;
            }
            $parts[] = number_format($mm / $unit->toMillimeters(), $precision, '.', '');
        }
        if (!$parts) {
            return '';
        }

        return implode(' × ', $parts) . ' ' . $unit->symbol();
    }

    public function __toString(): string
    {
        return $this->format(Unit::CM);
    }
}
