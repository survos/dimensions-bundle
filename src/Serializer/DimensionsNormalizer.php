<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Serializer;

use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\ShapeContracts\Shape;
use Survos\ShapeContracts\Unit;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class DimensionsNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private const DISPLAY_UNITS = [Unit::CM, Unit::IN, Unit::MM];

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if ($object instanceof Shape) {
            $shape = $object;
            $dimensions = Dimensions::fromShape($object);
        } else {
            assert($object instanceof Dimensions);
            $dimensions = $object;
            $shape = $object->toShape();
        }

        $display = [];
        foreach (self::DISPLAY_UNITS as $unit) {
            $display[$unit->value] = $dimensions->isEmpty ? null : $dimensions->format($unit);
        }

        return $shape->toNormalizedArray() + ['display' => $display];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Dimensions || $data instanceof Shape;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Dimensions|Shape
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Expected array for Dimensions denormalization.');
        }

        $shape = Shape::fromNormalizedArray($this->normalizeInputKeys($data));

        return $type === Shape::class ? $shape : Dimensions::fromShape($shape);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return in_array($type, [Dimensions::class, Shape::class], true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Dimensions::class => true, Shape::class => true];
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalizeInputKeys(array $data): array
    {
        foreach (['width', 'height', 'depth', 'length', 'thickness', 'diameter'] as $key) {
            $mmKey = $key . 'Mm';
            $legacyMmKey = $key . '_mm';
            if (!array_key_exists($mmKey, $data) && array_key_exists($legacyMmKey, $data)) {
                $data[$mmKey] = $data[$legacyMmKey];
            }
            if (isset($data[$key]) && is_array($data[$key])) {
                $entry = $data[$key];
                $unit = Unit::fromAlias((string) ($entry['unit'] ?? 'mm'));
                $data[$mmKey] = (int) round((float) ($entry['value'] ?? 0) * $unit->toMillimeters());
            }
        }

        return $data;
    }
}
