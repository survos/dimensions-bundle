<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Serializer;

use Survos\DimensionsBundle\ValueObject\Dimension;
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\DimensionsBundle\ValueObject\Unit;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes Dimensions to a rich JSON representation including pre-computed
 * display strings for all common units. Accepts both int-mm and {value, unit} input.
 */
final class DimensionsNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private const DISPLAY_UNITS = [Unit::CM, Unit::IN, Unit::MM];

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        assert($object instanceof Dimensions);

        $display = [];
        foreach (self::DISPLAY_UNITS as $unit) {
            $display[$unit->value] = $object->isEmpty ? null : $object->format($unit);
        }

        return [
            'width_mm'  => $object->widthMm,
            'height_mm' => $object->heightMm,
            'depth_mm'  => $object->depthMm,
            'display'   => $display,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Dimensions;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Dimensions
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Expected array for Dimensions denormalization.');
        }

        $resolveAxis = static function (array $data, string $key): ?int {
            // int mm form: {"width_mm": 216}
            $mmKey = $key . '_mm';
            if (isset($data[$mmKey])) {
                return (int) $data[$mmKey];
            }

            // {value, unit} form: {"width": {"value": 8.5, "unit": "in"}}
            if (isset($data[$key]) && is_array($data[$key])) {
                $entry = $data[$key];
                $unit  = Unit::from($entry['unit'] ?? 'mm');
                return (int) round((float) ($entry['value'] ?? 0) * $unit->toMillimeters());
            }

            return null;
        };

        return new Dimensions(
            $resolveAxis($data, 'width'),
            $resolveAxis($data, 'height'),
            $resolveAxis($data, 'depth'),
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === Dimensions::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Dimensions::class => true];
    }
}
