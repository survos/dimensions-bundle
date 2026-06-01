<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Survos\ShapeContracts\Length;

/**
 * Custom DBAL type for a single Length stored as INTEGER (mm).
 * Use #[ORM\Column(type: DimensionType::NAME)] for single-axis columns
 * like thickness, diameter, paper_weight_mm, etc.
 *
 * Extends the base Type (not IntegerType): IntegerType pins convertToPHPValue() to ": ?int", which is
 * not covariant with our ": ?Length" return. We declare the integer SQL column ourselves instead.
 */
final class DimensionType extends Type
{
    public const NAME = 'dimension';

    public function getName(): string
    {
        return self::NAME;
    }

    /** Stored as INTEGER (millimeters). */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Length
    {
        if ($value === null) {
            return null;
        }
        return new Length((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Length) {
            return $value->millimeters;
        }
        return (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
