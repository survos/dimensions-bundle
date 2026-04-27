<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\ValueObject;

use Doctrine\ORM\Mapping as ORM;

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

    // PHP 8.4 virtual properties for typed axis access
    public ?Dimension $width  { get => $this->widthMm  !== null ? new Dimension($this->widthMm)  : null; }
    public ?Dimension $height { get => $this->heightMm !== null ? new Dimension($this->heightMm) : null; }
    public ?Dimension $depth  { get => $this->depthMm  !== null ? new Dimension($this->depthMm)  : null; }

    /** Area in mm². Null when width or height is missing. */
    public ?int $area {
        get => $this->widthMm !== null && $this->heightMm !== null
            ? $this->widthMm * $this->heightMm
            : null;
    }

    /** Volume in mm³. Null when any axis is missing. */
    public ?int $volume {
        get => $this->widthMm !== null && $this->heightMm !== null && $this->depthMm !== null
            ? $this->widthMm * $this->heightMm * $this->depthMm
            : null;
    }

    public bool $is2D    { get => $this->depthMm === null; }
    public bool $isEmpty { get => $this->widthMm === null && $this->heightMm === null && $this->depthMm === null; }

    /** Depth below this threshold (mm) counts as "flat" — e.g., a sheet of paper. */
    public function isFlat(int $thresholdMm = 5): bool
    {
        return $this->depthMm === null || $this->depthMm < $thresholdMm;
    }

    /** Format using the given unit. Locale-independent (explicit decimal point). */
    public function format(Unit $unit = Unit::CM, int $precision = 1): string
    {
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
