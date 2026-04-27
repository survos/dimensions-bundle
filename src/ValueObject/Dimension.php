<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\ValueObject;

final readonly class Dimension implements \Stringable
{
    public function __construct(public int $millimeters)
    {
        if ($millimeters < 0) {
            throw new \InvalidArgumentException('Dimension cannot be negative.');
        }
    }

    // PHP 8.4 virtual properties — no backing store, computed on access
    public float $cm      { get => $this->millimeters / 10.0; }
    public float $inches  { get => $this->millimeters / 25.4; }
    public float $feet    { get => $this->millimeters / 304.8; }
    public float $meters  { get => $this->millimeters / 1000.0; }

    public static function fromMm(int $mm): self
    {
        return new self($mm);
    }

    public static function fromCm(float $cm): self
    {
        return new self((int) round($cm * 10));
    }

    public static function fromMeters(float $m): self
    {
        return new self((int) round($m * 1000));
    }

    public static function fromInches(float $in): self
    {
        return new self((int) round($in * 25.4));
    }

    public static function fromFeet(float $ft): self
    {
        return new self((int) round($ft * 304.8));
    }

    public static function from(float $value, Unit $unit): self
    {
        return new self((int) round($value * $unit->toMillimeters()));
    }

    public function toMm(): int
    {
        return $this->millimeters;
    }

    public function to(Unit $unit): float
    {
        return $this->millimeters / $unit->toMillimeters();
    }

    public function equals(self $other): bool
    {
        return $this->millimeters === $other->millimeters;
    }

    public function isLessThan(self $other): bool
    {
        return $this->millimeters < $other->millimeters;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->millimeters > $other->millimeters;
    }

    public function plus(self $other): self
    {
        return new self($this->millimeters + $other->millimeters);
    }

    public function minus(self $other): self
    {
        return new self($this->millimeters - $other->millimeters);
    }

    public function __toString(): string
    {
        return $this->millimeters . ' mm';
    }
}
