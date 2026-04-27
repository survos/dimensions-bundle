<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\ValueObject;

enum Unit: string
{
    case MM = 'mm';
    case CM = 'cm';
    case M  = 'm';
    case IN = 'in';
    case FT = 'ft';

    public function toMillimeters(): float
    {
        return match ($this) {
            self::MM => 1.0,
            self::CM => 10.0,
            self::M  => 1000.0,
            self::IN => 25.4,
            self::FT => 304.8,
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }
}
