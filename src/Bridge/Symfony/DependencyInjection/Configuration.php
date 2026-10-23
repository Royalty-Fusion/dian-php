<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the dian: root namespace.
 *
 *   dian:
 *     environment: habilitacion # or 'produccion'
 *     nit: '900123456'              # NIT del facturador (dian:doctor)
 *     signer_role: supplier         # or 'third party' for proveedores tecnológicos
 *     certificate:
 *       path: '%kernel.project_dir%/config/certs/empresa.p12'
 *       password: '%env(DIAN_CERT_PASSWORD)%'
 *     software:
 *       id: '%env(DIAN_SOFT_ID)%'
 *       pin: '%env(DIAN_SOFT_PIN)%'
 *       provider_nit: '900123456'
 *       provider_code: '000'          # PPP in the file names
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
        /** @var ArrayNodeDefinition $root Symfony 6.4 types getRootNode() as the generic NodeDefinition */
        $root = $tree->getRootNode();

        // One statement per node: Symfony 6.4 types end() loosely, so long
        // fluent chains do not type-check there.
        $children = $root->children();
        $children->enumNode('environment')->values(['habilitacion', 'produccion'])->defaultValue('habilitacion');
        $children->booleanNode('validate_before_send')->defaultTrue();
        $children->scalarNode('nit')->defaultValue('');
        $children->enumNode('signer_role')->values(['supplier', 'third party'])->defaultValue('supplier');

        $certificate = $children->arrayNode('certificate')->isRequired()->children();
        $certificate->scalarNode('path')->isRequired();
        $certificate->scalarNode('password')->isRequired();

        $software = $children->arrayNode('software')->addDefaultsIfNotSet()->children();
        $software->scalarNode('id')->defaultValue('');
        $software->scalarNode('pin')->defaultValue('');
        $software->scalarNode('provider_nit')->defaultValue('');
        $software->scalarNode('provider_code')->defaultValue('000');

        $report = $children->arrayNode('report')->addDefaultsIfNotSet()->children();
        $report->scalarNode('logo_url')->defaultValue('');
        $report->scalarNode('accent_color')->defaultValue('#1e3a8a');

        return $tree;
    }
}
