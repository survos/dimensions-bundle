# survos/dimensions-bundle

Physical dimensions value objects and Doctrine integration for Symfony — designed for archival, museum, and document workflows.

Store everything as integer millimeters. Display in any unit. Query with real columns.

## The problem

Museum objects, archival items, and documents need physical dimensions. The naive approach —
storing floats in a JSON blob or ad-hoc `width_cm`/`height_in` columns — breaks querying,
causes unit confusion, and makes display logic sprawl across templates.

## The solution

```php
use Survos\DimensionsBundle\ValueObject\Dimensions;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ArchivalItem
{
    #[ORM\Embedded(class: Dimensions::class, columnPrefix: false)]
    private ?Dimensions $dimensions = null;
}
```

This creates three nullable integer columns (`width_mm`, `height_mm`, `depth_mm`) — real columns,
real indexes, real range queries. Display formatting, unit conversion, and parsing happen in PHP.

## Installation

```bash
composer require survos/dimensions-bundle
```

The custom `dimension` DBAL type and Twig filters are registered automatically.

## Core value objects

### `Unit` enum

```php
use Survos\DimensionsBundle\ValueObject\Unit;

Unit::CM->toMillimeters(); // 10.0
Unit::IN->toMillimeters(); // 25.4
Unit::FT->toMillimeters(); // 304.8
```

### `Dimension` (single axis)

```php
use Survos\DimensionsBundle\ValueObject\Dimension;

$d = Dimension::fromInches(8.5);    // Dimension(216)
$d = Dimension::from(21.6, Unit::CM); // Dimension(216)

// PHP 8.4 property hooks — computed, no backing store
$d->cm;     // 21.6
$d->inches; // 8.503...
$d->feet;   // 0.708...
$d->meters; // 0.216

$d->to(Unit::IN); // float
(string) $d;      // "216 mm"
```

### `Dimensions` (composite W × H × D)

```php
use Survos\DimensionsBundle\ValueObject\Dimensions;

$dims = new Dimensions(widthMm: 216, heightMm: 279);      // Letter paper
$dims = new Dimensions(widthMm: 216, heightMm: 279, depthMm: 25); // With depth

// PHP 8.4 property hooks
$dims->width;   // ?Dimension(216)
$dims->height;  // ?Dimension(279)
$dims->depth;   // null (or Dimension)
$dims->area;    // ?int — mm²
$dims->volume;  // ?int — mm³
$dims->is2D;    // true when depth is null
$dims->isEmpty; // true when all axes are null

$dims->isFlat(5);           // true when depth < 5 mm (e.g., a sheet of paper)
$dims->format(Unit::CM);    // "21.6 × 27.9 cm"
(string) $dims;             // "21.6 × 27.9 cm" (locale-independent)
```

## Doctrine integration

### Option A: Embeddable (recommended)

```php
#[ORM\Embedded(class: Dimensions::class, columnPrefix: false)]
private ?Dimensions $dimensions = null;
```

Generates columns: `width_mm INTEGER`, `height_mm INTEGER`, `depth_mm INTEGER` (all nullable).

Use `columnPrefix: 'physical_'` to namespace the columns when an entity has multiple dimension sets
(e.g., `physical_width_mm`, `mount_width_mm`).

### Option B: Custom type for a single axis

```php
use Survos\DimensionsBundle\Doctrine\DimensionType;

#[ORM\Column(type: DimensionType::NAME, nullable: true)]
private ?Dimension $thickness = null;
```

Stored as `INTEGER`, hydrated to a `Dimension` value object.

## Parsing

```php
use Survos\DimensionsBundle\Parser\DimensionParser;

$parser = new DimensionParser();

$parser->parseDimension('8.5 in');          // Dimension(216)
$parser->parseDimension('4ft 2in');         // Dimension(1270)
$parser->parseDimension('216');             // Dimension(216) — assumes default unit (mm)

$parser->parseDimensions('8.5 × 11 in');    // Dimensions(216, 279, null)
$parser->parseDimensions('21.6 x 27.9 cm'); // Dimensions(216, 279, null)
$parser->parseDimensions('10 × 12 × 3 in'); // Dimensions(254, 305, 76)
```

Accepts `×` (Unicode), `x`, `X` as separators. Whitespace tolerant. Default unit is configurable.

## Twig filters

```twig
{# Composite dimensions #}
{{ item.dimensions|dim }}                  {# "21.6 × 27.9 cm" #}
{{ item.dimensions|dim('in', 2) }}         {# "8.50 × 11.00 in" #}
{{ item.dimensions|dim_both }}             {# "21.6 × 27.9 cm (8.5 × 11.0 in)" #}

{# Single dimension #}
{{ item.thickness|dim_value }}             {# "0.3 mm" #}
{{ item.thickness|dim_value('mm', 0) }}    {# "0" #}

{# Area & volume #}
{{ item.dimensions|dim_area('cm') }}       {# "602.6 cm²" #}
{{ item.dimensions|dim_volume('cm') }}     {# "1807.7 cm³" #}
```

All filters are null-safe — an empty or null `Dimensions` returns `''`.

## Form types

```php
use Survos\DimensionsBundle\Form\DimensionsType;
use Survos\DimensionsBundle\ValueObject\Unit;

$builder->add('dimensions', DimensionsType::class, [
    'display_unit' => 'cm',    // user sees cm; stored as int mm
    'allow_depth'  => true,
    'required'     => false,
]);
```

For a single axis (e.g., thickness):

```php
use Survos\DimensionsBundle\Form\DimensionType;

$builder->add('thickness', DimensionType::class, [
    'display_unit' => 'mm',
]);
```

## Serializer / API Platform

`DimensionsNormalizer` outputs width/height/depth in mm plus pre-computed display strings:

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

Denormalization accepts either int-mm keys or `{value, unit}` objects:

```json
{ "width": { "value": 8.5, "unit": "in" }, "height": { "value": 11, "unit": "in" } }
```

## Configuration

```yaml
# config/packages/survos_dimensions.yaml
survos_dimensions:
    default_display_unit: cm    # mm | cm | m | in | ft
    default_input_unit:   mm
    default_precision:    1
    show_both_units:      false  # when true, formatters output "21.6 cm (8.5 in)"
    flat_threshold_mm:    5
```

### Per-user display unit

Implement `DimensionPreferenceInterface` and register it as a service to override the
display unit per user, institution, or session:

```php
use Survos\DimensionsBundle\Service\DimensionPreferenceInterface;

class UserDimensionPreference implements DimensionPreferenceInterface
{
    public function __construct(private readonly Security $security) {}

    public function getPreferredDisplayUnit(): ?string
    {
        return $this->security->getUser()?->getPreferredUnit(); // 'in' for US institutions
    }
}
```

## Status

PHP 8.4+ · Symfony 7/8 · Doctrine ORM 3
