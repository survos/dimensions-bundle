# survos/dimensions-bundle

A Symfony bundle for modeling physical dimensions in archival, museum, and document-handling applications. Stores everything as integer millimeters internally; handles display, conversion, and Doctrine persistence.

## Design principles

1. **Integer millimeters as the canonical unit.** No floats in storage. All conversions happen at the edges (input parsing, display formatting).
2. **Immutable value objects.** `Dimension` and `Dimensions` are `final readonly` classes. Modeled after `moneyphp/money`.
3. **Doctrine embeddables, not JSON.** Dimensions become real, queryable columns on the parent entity (`width_mm`, `height_mm`, `depth_mm`).
4. **Multi-unit display, single-unit storage.** Store mm, display in user/institution preference (cm, in, ft, mm).
5. **Optional axes.** A photograph has no meaningful depth. A scroll has no meaningful height when rolled. Don't force three dimensions when two will do.
6. **Symfony 8 / PHP 8.4+ idioms.** Constructor property promotion, readonly, enums, `#[Argument('desc')]` style attributes.

## Package layout

```
survos/dimensions-bundle/
├── composer.json
├── src/
│   ├── SurvosDimensionsBundle.php
│   ├── DependencyInjection/
│   │   ├── Configuration.php
│   │   └── SurvosDimensionsExtension.php
│   ├── ValueObject/
│   │   ├── Dimension.php
│   │   ├── Dimensions.php
│   │   └── Unit.php                  # enum: MM, CM, M, IN, FT
│   ├── Doctrine/
│   │   ├── DimensionType.php         # custom Doctrine type (single dim as int column)
│   │   └── (Dimensions is an Embeddable, not a custom type)
│   ├── Form/
│   │   ├── DimensionType.php         # Symfony form type
│   │   └── DimensionsType.php
│   ├── Twig/
│   │   └── DimensionsExtension.php
│   ├── Serializer/
│   │   └── DimensionsNormalizer.php  # for API Platform / Symfony serializer
│   ├── Service/
│   │   └── DimensionFormatter.php    # display logic, locale-aware
│   └── Parser/
│       └── DimensionParser.php       # parses "8.5 in", "4ft 2in", "21.6 × 27.9 cm"
├── tests/
│   ├── ValueObject/
│   ├── Doctrine/
│   └── Parser/
└── README.md
```

## Core value objects

### `Unit` enum

```php
namespace Survos\DimensionsBundle\ValueObject;

enum Unit: string {
    case MM = 'mm';
    case CM = 'cm';
    case M  = 'm';
    case IN = 'in';
    case FT = 'ft';

    /** Conversion factor from this unit to mm. */
    public function toMillimeters(): float {
        return match($this) {
            self::MM => 1.0,
            self::CM => 10.0,
            self::M  => 1000.0,
            self::IN => 25.4,
            self::FT => 304.8,
        };
    }

    public function symbol(): string {
        return $this->value;
    }
}
```

### `Dimension` (single axis)

```php
namespace Survos\DimensionsBundle\ValueObject;

final readonly class Dimension implements \Stringable {
    public function __construct(public int $millimeters) {
        if ($millimeters < 0) {
            throw new \InvalidArgumentException('Dimension cannot be negative.');
        }
    }

    public static function fromMm(int $mm): self {
        return new self($mm);
    }

    public static function fromCm(float $cm): self {
        return new self((int) round($cm * 10));
    }

    public static function fromMeters(float $m): self {
        return new self((int) round($m * 1000));
    }

    public static function fromInches(float $in): self {
        return new self((int) round($in * 25.4));
    }

    public static function fromFeet(float $ft): self {
        return new self((int) round($ft * 304.8));
    }

    public static function from(float $value, Unit $unit): self {
        return new self((int) round($value * $unit->toMillimeters()));
    }

    public function toMm(): int       { return $this->millimeters; }
    public function toCm(): float     { return $this->millimeters / 10; }
    public function toMeters(): float { return $this->millimeters / 1000; }
    public function toInches(): float { return $this->millimeters / 25.4; }
    public function toFeet(): float   { return $this->millimeters / 304.8; }

    public function to(Unit $unit): float {
        return $this->millimeters / $unit->toMillimeters();
    }

    public function equals(self $other): bool {
        return $this->millimeters === $other->millimeters;
    }

    public function isLessThan(self $other): bool {
        return $this->millimeters < $other->millimeters;
    }

    public function isGreaterThan(self $other): bool {
        return $this->millimeters > $other->millimeters;
    }

    public function plus(self $other): self {
        return new self($this->millimeters + $other->millimeters);
    }

    public function minus(self $other): self {
        return new self($this->millimeters - $other->millimeters);
    }

    public function __toString(): string {
        return $this->millimeters . ' mm';
    }
}
```

### `Dimensions` (composite W × H × D)

```php
namespace Survos\DimensionsBundle\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Dimensions implements \Stringable {
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

    public function width(): ?Dimension {
        return $this->widthMm !== null ? new Dimension($this->widthMm) : null;
    }

    public function height(): ?Dimension {
        return $this->heightMm !== null ? new Dimension($this->heightMm) : null;
    }

    public function depth(): ?Dimension {
        return $this->depthMm !== null ? new Dimension($this->depthMm) : null;
    }

    /** Area in mm². Null if width or height missing. */
    public function area(): ?int {
        if ($this->widthMm === null || $this->heightMm === null) {
            return null;
        }
        return $this->widthMm * $this->heightMm;
    }

    /** Volume in mm³. Null if any axis missing. */
    public function volume(): ?int {
        if ($this->widthMm === null || $this->heightMm === null || $this->depthMm === null) {
            return null;
        }
        return $this->widthMm * $this->heightMm * $this->depthMm;
    }

    public function isFlat(int $thresholdMm = 5): bool {
        return $this->depthMm === null || $this->depthMm < $thresholdMm;
    }

    public function is2D(): bool {
        return $this->depthMm === null;
    }

    public function isEmpty(): bool {
        return $this->widthMm === null && $this->heightMm === null && $this->depthMm === null;
    }

    public function __toString(): string {
        return $this->format(Unit::CM);
    }

    /** Default formatting; for richer formatting use DimensionFormatter service. */
    public function format(Unit $unit = Unit::CM, int $precision = 1): string {
        $parts = [];
        foreach ([$this->widthMm, $this->heightMm, $this->depthMm] as $mm) {
            if ($mm === null) continue;
            $parts[] = number_format($mm / $unit->toMillimeters(), $precision);
        }
        if (!$parts) return '';
        return implode(' × ', $parts) . ' ' . $unit->symbol();
    }
}
```

## Doctrine integration

Two options, both supported by the bundle:

### Option A: Embeddable (recommended for primary W×H×D on entities)

```php
use Survos\DimensionsBundle\ValueObject\Dimensions;

#[ORM\Entity]
class ArchivalItem {
    #[ORM\Embedded(class: Dimensions::class, columnPrefix: false)]
    private ?Dimensions $dimensions = null;

    public function getDimensions(): ?Dimensions { return $this->dimensions; }
    public function setDimensions(?Dimensions $d): void { $this->dimensions = $d; }
}
```

Generates columns: `width_mm`, `height_mm`, `depth_mm` (all nullable int). Use `columnPrefix: 'physical_'` if you need namespacing (e.g., separate `physical_*` and `mount_*` dimensions on the same entity).

### Option B: Custom Doctrine type for a single `Dimension`

For cases where you want just one dimension (e.g., `thickness`, `diameter`, `paper_thickness_mm`):

```php
use Survos\DimensionsBundle\Doctrine\DimensionType;

#[ORM\Column(type: DimensionType::NAME, nullable: true)]
private ?Dimension $thickness = null;
```

`DimensionType` stores as `INTEGER`, hydrates to `Dimension` value object.

## Twig extension

```twig
{# Composite dimensions #}
{{ item.dimensions|dim }}                  {# default unit (cm), default precision #}
{{ item.dimensions|dim('in', 2) }}         {# "8.50 × 11.00 in" #}
{{ item.dimensions|dim_both }}             {# "21.6 × 27.9 cm (8.5 × 11.0 in)" #}

{# Single dimension #}
{{ item.thickness|dim_value }}             {# "0.3 mm" #}
{{ item.thickness|dim_value('mm', 0) }}    {# "0" — useful for hairline sheets #}

{# Area & volume #}
{{ item.dimensions|dim_area('cm') }}       {# "602.6 cm²" #}
{{ item.dimensions|dim_volume('cm') }}     {# "1807.7 cm³" #}
```

The user's preferred display unit comes from a `DimensionFormatter` service, which reads from (in order): explicit filter argument → Twig global / request attribute (per-user setting) → bundle config default.

## Form types

```php
use Survos\DimensionsBundle\Form\DimensionsType;

$builder->add('dimensions', DimensionsType::class, [
    'input_unit'   => Unit::MM,    // what's stored
    'display_unit' => Unit::CM,    // what user sees in input fields
    'allow_depth'  => true,
    'required'     => false,
]);
```

The form renders three numeric inputs (W / H / D) with the display unit as a suffix. On submit, values are converted to int mm and a `Dimensions` object is constructed.

A future enhancement: a single text-field `DimensionsTextType` that parses `"8.5 × 11 in"` or `"21.6 x 27.9 cm"` via the `DimensionParser`.

## Parser

```php
$parser = new DimensionParser();

$parser->parseDimension('8.5 in');           // Dimension(216)
$parser->parseDimension('4ft 2in');          // Dimension(1270)
$parser->parseDimension('216');              // Dimension(216) — assumes default unit
$parser->parseDimensions('8.5 × 11 in');     // Dimensions(216, 279, null)
$parser->parseDimensions('21.6 x 27.9 cm');  // Dimensions(216, 279, null)
$parser->parseDimensions('10 × 12 × 3 in');  // Dimensions(254, 305, 76)
```

Accepts `×`, `x`, `X` as separators. Whitespace tolerant. Defaults to a configurable input unit when no unit suffix is present.

## API Platform / Serializer

`DimensionsNormalizer` outputs:

```json
{
  "width_mm": 216,
  "height_mm": 279,
  "depth_mm": null,
  "display": {
    "cm": "21.6 × 27.9 cm",
    "in": "8.5 × 11.0 in",
    "mm": "216 × 279 mm"
  }
}
```

Denormalization accepts either the `*_mm` ints directly or a `{value, unit}` form:

```json
{ "width": { "value": 8.5, "unit": "in" }, "height": { "value": 11, "unit": "in" } }
```

## Bundle configuration

```yaml
# config/packages/survos_dimensions.yaml
survos_dimensions:
    default_display_unit: cm   # cm | mm | m | in | ft
    default_input_unit:   mm
    default_precision:    1
    show_both_units:      false  # when true, formatters output "21.6 cm (8.5 in)"
    flat_threshold_mm:    5      # below this depth, isFlat() returns true
```

User-level override: a `DimensionPreference` interface that institutions can implement to scope display units per user, per institution, or per session.

## Testing requirements

- Round-trip conversions: every (value, unit) → mm → (value, unit) within rounding tolerance.
- Boundary cases: 0 mm, very large (space-shuttle scale), Letter/Legal/A4 paper.
- Doctrine: persist + hydrate an entity with embedded `Dimensions`; verify column names and nullable behavior.
- Parser: edge cases including unicode `×`, mixed-unit composite ("4ft 2in × 3ft"), missing axes.
- Twig: each filter with each unit; Both-units output; null-safe (no exception on empty Dimensions).
- Format stability: `(string) $dimensions` is locale-independent (uses `.` as decimal — number_format with explicit args).

## composer.json sketch

```json
{
    "name": "survos/dimensions-bundle",
    "description": "Physical dimensions value objects and Doctrine integration for Symfony — designed for archival, museum, and document workflows.",
    "type": "symfony-bundle",
    "license": "MIT",
    "require": {
        "php": ">=8.4",
        "symfony/framework-bundle": "^7.0|^8.0",
        "doctrine/orm": "^3.0",
        "doctrine/doctrine-bundle": "^2.10"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "symfony/twig-bundle": "^7.0|^8.0",
        "symfony/form": "^7.0|^8.0",
        "symfony/serializer": "^7.0|^8.0",
        "api-platform/core": "^4.0"
    },
    "suggest": {
        "symfony/twig-bundle": "To use the Twig filters",
        "symfony/form": "To use DimensionsType form type",
        "api-platform/core": "For automatic API Platform integration"
    },
    "autoload": {
        "psr-4": { "Survos\\DimensionsBundle\\": "src/" }
    }
}
```

## Open questions for implementation

1. **Locale-aware number formatting** — should `format()` respect the active locale (1 234,5 cm in fr_FR)? Default off, opt-in via formatter service.
2. **Tolerances** — do we need a `Dimension::approximatelyEquals(other, toleranceMm)` for "close enough" comparisons in physical-object matching?
3. **Display unit per axis** — almost certainly no, but flagging: depth is sometimes shown in mm while W/H are in cm (e.g., books). Punt unless requested.
4. **Imperial fractional display** — `8 1/2 in` vs `8.5 in`. Probably not worth the complexity for v1; archival audiences are fine with decimals.
5. **Integration with `survos/meili-bundle`** — should embedded `Dimensions` automatically expose searchable/filterable attributes? Likely yes via a normalizer that flattens to `width_mm`, `height_mm`, `depth_mm`, `area_mm2`, `volume_mm3` for filtering.

## Build order for Claude Code

1. Skeleton: composer.json, bundle class, DI extension, configuration.
2. `Unit` enum + `Dimension` value object + tests.
3. `Dimensions` embeddable + tests (with in-memory SQLite Doctrine integration test).
4. `DimensionType` Doctrine custom type for single-dimension columns.
5. `DimensionParser` + tests.
6. `DimensionFormatter` service + Twig extension + tests.
7. Form types.
8. Serializer normalizer.
9. README with usage examples for ScanStationAI / Museado / Scanseum.
