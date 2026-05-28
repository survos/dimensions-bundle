<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Form;

use Survos\ShapeContracts\Length;
use Survos\ShapeContracts\Unit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Single-axis dimension form type (for thickness, diameter, etc.).
 * Renders as a number input; converts between display unit and mm on submit.
 */
final class DimensionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $displayUnit = Unit::fromAlias($options['display_unit']);

        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?Length $dim): ?string => $dim !== null ? (string) round($dim->to($displayUnit), 4) : null,
            static fn (?string $value): ?Length => ($value !== null && $value !== '')
                    ? new Length((int) round((float) $value * $displayUnit->toMillimeters()))
                    : null,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'display_unit' => 'mm',
            'attr'         => ['step' => 'any', 'min' => '0'],
        ]);
        $resolver->setAllowedValues('display_unit', array_column(Unit::cases(), 'value'));
    }

    public function getParent(): string
    {
        return NumberType::class;
    }
}
