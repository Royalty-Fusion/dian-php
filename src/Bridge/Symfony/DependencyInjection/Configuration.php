<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the dian: root namespace.
 *
 *   dian:
 *     environment: habilitacion # or 'produccion'
 *     certificate:
 *       path: '%kernel.project_dir%/config/certs/empresa.p12'
 *       password: '%env(DIAN_CERT_PASSWORD)%'
 *     software:
 *       id: '%env(DIAN_SOFT_ID)%'
 *       pin: '%env(DIAN_SOFT_PIN)%'
 *       provider_nit: '900123456'
 *     validate_before_send: true
 *     report:
 *       logo_url: '/img/logo.png'
 *       accent_color: '#1e3a8a'
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('dian');
        $root = $tree->getRootNode();

        $root
            ->children()
                ->enumNode('environment')->values(['habilitacion', 'produccion'])->defaultValue('habilitacion')->end()
                ->booleanNode('validate_before_send')->defaultTrue()->end()
                ->arrayNode('certificate')
                    ->isRequired()
                    ->children()
                        ->scalarNode('path')->isRequired()->end()
                        ->scalarNode('password')->isRequired()->end()
                    ->end()
                ->end()
                ->arrayNode('software')
                    ->children()
                        ->scalarNode('id')->defaultValue('')->end()
                        ->scalarNode('pin')->defaultValue('')->end()
                        ->scalarNode('provider_nit')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('report')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('logo_url')->defaultValue('')->end()
                        ->scalarNode('accent_color')->defaultValue('#1e3a8a')->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
