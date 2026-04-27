<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Survos\DimensionsBundle\ValueObject\Dimension;

/**
 * Custom DBAL type for a single Dimension stored as INTEGER (mm).
 * Use #[ORM\Column(type: DimensionType::NAME)] for single-axis columns
 * like thickness, diameter, paper_weight_mm, etc.
 */
final class DimensionType extends IntegerType
{
    public const NAME = 'dimension';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Dimension
    {
        if ($value === null) {
            return null;
        }
        return new Dimension((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Dimension) {
            return $value->millimeters;
        }
        return (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
