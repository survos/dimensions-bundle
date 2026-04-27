<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle;

use Survos\DimensionsBundle\Doctrine\DimensionType as DoctrineDimensionType;
use Survos\DimensionsBundle\Form\DimensionType as DimensionFormType;
use Survos\DimensionsBundle\Form\DimensionsType;
use Survos\DimensionsBundle\Parser\DimensionParser;
use Survos\DimensionsBundle\Serializer\DimensionsNormalizer;
use Survos\DimensionsBundle\Service\DimensionFormatter;
use Survos\DimensionsBundle\Twig\DimensionsExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosDimensionsBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_display_unit')->defaultValue('cm')->end()
                ->scalarNode('default_input_unit')->defaultValue('mm')->end()
                ->integerNode('default_precision')->defaultValue(1)->end()
                ->booleanNode('show_both_units')->defaultFalse()->end()
                ->integerNode('flat_threshold_mm')->defaultValue(5)->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(DimensionFormatter::class)->public()
            ->arg('$defaultDisplayUnit', $config['default_display_unit'])
            ->arg('$defaultPrecision', $config['default_precision'])
            ->arg('$showBothUnits', $config['show_both_units']);

        $services->set(DimensionParser::class)->public()
            ->arg('$defaultUnit', $config['default_input_unit']);

        // Twig integration (optional — only if Twig is installed)
        if (class_exists(\Twig\Extension\AbstractExtension::class)) {
            $services->set(DimensionsExtension::class)->public();
        }

        // Serializer/API Platform integration (optional)
        if (interface_exists(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)) {
            $services->set(DimensionsNormalizer::class)->public();
        }

        // Form types (optional)
        if (class_exists(\Symfony\Component\Form\AbstractType::class)) {
            $services->set(DimensionFormType::class)->public();
            $services->set(DimensionsType::class)->public();
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Auto-register the custom DBAL type so users don't need to add it manually
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => [
                        DoctrineDimensionType::NAME => DoctrineDimensionType::class,
                    ],
                ],
            ]);
        }
    }
}
