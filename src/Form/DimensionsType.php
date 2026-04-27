<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Form;

use Survos\DimensionsBundle\ValueObject\Dimensions;
use Survos\DimensionsBundle\ValueObject\Unit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Compound form type for W × H × D.
 *
 * Usage:
 *   $builder->add('dimensions', DimensionsType::class, [
 *       'display_unit' => 'cm',
 *       'allow_depth'  => true,
 *   ]);
 */
final class DimensionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $displayUnit = Unit::from($options['display_unit']);
        $sym         = $displayUnit->symbol();

        $numberOpts = ['required' => false, 'scale' => 4, 'attr' => ['step' => 'any', 'min' => '0']];

        $builder
            ->add('width',  NumberType::class, array_merge($numberOpts, ['label' => "Width ({$sym})"]))
            ->add('height', NumberType::class, array_merge($numberOpts, ['label' => "Height ({$sym})"]));

        if ($options['allow_depth']) {
            $builder->add('depth', NumberType::class, array_merge($numberOpts, ['label' => "Depth ({$sym})"]));
        }

        $toDisplay = static fn (?int $mm) use ($displayUnit): ?float
            => $mm !== null ? round($mm / $displayUnit->toMillimeters(), 4) : null;

        $toMm = static fn (mixed $v) use ($displayUnit): ?int
            => ($v !== null && $v !== '') ? (int) round((float) $v * $displayUnit->toMillimeters()) : null;

        $builder->addModelTransformer(new CallbackTransformer(
            static function (?Dimensions $dims) use ($toDisplay): array {
                return [
                    'width'  => $toDisplay($dims?->widthMm),
                    'height' => $toDisplay($dims?->heightMm),
                    'depth'  => $toDisplay($dims?->depthMm),
                ];
            },
            static function (array $data) use ($toMm): ?Dimensions {
                $w = $toMm($data['width']  ?? null);
                $h = $toMm($data['height'] ?? null);
                $d = $toMm($data['depth']  ?? null);
                if ($w === null && $h === null && $d === null) {
                    return null;
                }
                return new Dimensions($w, $h, $d);
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $validUnits = array_column(Unit::cases(), 'value');

        $resolver->setDefaults([
            'display_unit' => 'cm',
            'allow_depth'  => true,
            'label'        => false,
        ]);

        $resolver->setAllowedValues('display_unit', $validUnits);
    }
}
