<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Service;

use Survos\DimensionsBundle\Parser\DimensionParser;
use Survos\ShapeContracts\Shape;

/**
 * Converts heterogeneous source-provided dimension data (free-text strings,
 * typed arrays, structured records) into the shape expected by
 * `PhysicalObjectDto::$dimensions` / `$dimensionsRaw` / `$weight`.
 *
 * Strategy:
 *   - structured records (containing height/width/length/depth/radius keys)
 *     pass through with units normalized
 *   - typed arrays like SMK `[{type:'height', value:'21.6', unit:'centimeter'}]`
 *     are folded into one record per `partType` group
 *   - free-text strings are first scanned for a parenthetical metric
 *     ("(9.2 × 3.2 cm)") and otherwise handed to {@see DimensionParser}
 *   - anything unparseable lands in `dimensionsRaw` so the UI can fall back
 *     on the literal source string
 */
final class DimensionsNormalizer
{
    private const DIMENSION_KEYS = ['height', 'width', 'length', 'depth', 'radius', 'diameter', 'thickness'];

    public function __construct(
        private readonly DimensionParser $parser,
    ) {}

    /**
     * @return array{dimensions: list<array<string,mixed>>|null, dimensionsRaw: string|null, weight: list<array<string,mixed>>|null}
     */
    public function normalize(mixed $source): array
    {
        $empty = ['dimensions' => null, 'dimensionsRaw' => null, 'weight' => null];

        if ($source === null || $source === '' || $source === []) {
            return $empty;
        }

        if (is_array($source)) {
            return $this->fromArray($source);
        }

        if (is_string($source)) {
            return $this->fromString($source);
        }

        return $empty;
    }

    /**
     * @param array<mixed> $source
     * @return array{dimensions: list<array<string,mixed>>|null, dimensionsRaw: string|null, weight: list<array<string,mixed>>|null}
     */
    private function fromArray(array $source): array
    {
        if ($this->looksLikeStructured($source)) {
            $records = [];
            foreach ($source as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $records[] = $this->normalizeStructuredRow($row);
            }
            return ['dimensions' => $records !== [] ? $records : null, 'dimensionsRaw' => null, 'weight' => null];
        }

        if ($this->looksLikeTyped($source)) {
            return $this->foldTyped($source);
        }

        return ['dimensions' => null, 'dimensionsRaw' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null, 'weight' => null];
    }

    /**
     * @return array{dimensions: list<array<string,mixed>>|null, dimensionsRaw: string|null, weight: list<array<string,mixed>>|null}
     */
    private function fromString(string $source): array
    {
        $source = trim($source);
        if ($source === '') {
            return ['dimensions' => null, 'dimensionsRaw' => null, 'weight' => null];
        }

        if (preg_match('/\(([^()]*(?:mm|cm|m|in|ft)[^()]*)\)/i', $source, $m)) {
            $record = $this->tryParseShape($m[1]);
            if ($record !== null) {
                return ['dimensions' => [$record], 'dimensionsRaw' => $source, 'weight' => null];
            }
        }

        $record = $this->tryParseShape($source);
        if ($record !== null) {
            return ['dimensions' => [$record], 'dimensionsRaw' => null, 'weight' => null];
        }

        return ['dimensions' => null, 'dimensionsRaw' => $source, 'weight' => null];
    }

    /** @return array<string,mixed>|null */
    private function tryParseShape(string $input): ?array
    {
        try {
            $shape = $this->parser->parseShape($input);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->shapeToRecord($shape);
    }

    /** @return array<string,mixed> */
    private function shapeToRecord(Shape $shape): array
    {
        $record = ['units' => 'cm'];
        if ($shape->width  !== null) { $record['width']  = round($shape->width->millimeters  / 10, 2); }
        if ($shape->height !== null) { $record['height'] = round($shape->height->millimeters / 10, 2); }
        if ($shape->depth  !== null) { $record['depth']  = round($shape->depth->millimeters  / 10, 2); }
        return $record;
    }

    /** @param array<mixed> $source */
    private function looksLikeStructured(array $source): bool
    {
        foreach ($source as $row) {
            if (!is_array($row)) {
                return false;
            }
            foreach (self::DIMENSION_KEYS as $key) {
                if (array_key_exists($key, $row)) {
                    return true;
                }
            }
            return false;
        }
        return false;
    }

    /** @param array<mixed> $source */
    private function looksLikeTyped(array $source): bool
    {
        foreach ($source as $row) {
            if (!is_array($row)) {
                return false;
            }
            return array_key_exists('type', $row) && array_key_exists('value', $row);
        }
        return false;
    }

    /** @param array<string,mixed> $row */
    private function normalizeStructuredRow(array $row): array
    {
        $out = [];
        foreach (self::DIMENSION_KEYS as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                $out[$key] = (float) $row[$key];
            }
        }
        if (isset($row['units']) && is_scalar($row['units'])) {
            $out['units'] = $this->canonicalUnit((string) $row['units']);
        }
        if (isset($row['name']) && is_scalar($row['name'])) {
            $out['name'] = (string) $row['name'];
        }
        return $out;
    }

    /**
     * @param array<mixed> $items
     * @return array{dimensions: list<array<string,mixed>>|null, dimensionsRaw: string|null, weight: list<array<string,mixed>>|null}
     */
    private function foldTyped(array $items): array
    {
        /** @var array<string,array<string,mixed>> $groups keyed by partType label */
        $groups = [];
        $weight = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = isset($item['type']) && is_scalar($item['type']) ? strtolower(trim((string) $item['type'])) : null;
            $value = isset($item['value']) && is_numeric($item['value']) ? (float) $item['value'] : null;
            $unit = isset($item['unit']) && is_scalar($item['unit']) ? $this->canonicalUnit((string) $item['unit']) : null;
            $label = '';
            foreach (['partType', 'part_type', 'notes'] as $labelKey) {
                if (isset($item[$labelKey]) && is_scalar($item[$labelKey])) {
                    $label = trim((string) $item[$labelKey]);
                    break;
                }
            }

            if ($type === null || $value === null) {
                continue;
            }

            if ($type === 'weight') {
                $entry = ['amount' => $value, 'units' => $unit ?? 'g'];
                if ($label !== '') {
                    $entry['name'] = $label;
                }
                $weight[] = $entry;
                continue;
            }

            if (!in_array($type, self::DIMENSION_KEYS, true)) {
                continue;
            }

            $groups[$label] ??= ['units' => $unit ?? 'cm'];
            $groups[$label][$type] = $value;
            if ($unit !== null) {
                $groups[$label]['units'] = $unit;
            }
            if ($label !== '' && !isset($groups[$label]['name'])) {
                $groups[$label]['name'] = $label;
            }
        }

        $dimensions = array_values($groups);

        return [
            'dimensions'    => $dimensions !== [] ? $dimensions : null,
            'dimensionsRaw' => null,
            'weight'        => $weight !== [] ? $weight : null,
        ];
    }

    private function canonicalUnit(string $unit): string
    {
        return match (strtolower(trim($unit))) {
            'millimeter', 'millimetre', 'millimeters', 'millimetres' => 'mm',
            'centimeter', 'centimetre', 'centimeters', 'centimetres' => 'cm',
            'meter', 'metre', 'meters', 'metres' => 'm',
            'inch', 'inches' => 'in',
            'foot', 'feet' => 'ft',
            'gram', 'grams', 'gramme', 'grammes' => 'g',
            'kilogram', 'kilograms' => 'kg',
            'pound', 'pounds', 'lb', 'lbs' => 'lb',
            'ounce', 'ounces' => 'oz',
            default => $unit,
        };
    }
}
