<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class DianExtension extends Extension
{
    public function getAlias(): string
    {
        return 'dian';
    }

    /**
     * @param array<array<string,mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('dian.environment', $config['environment']);
        $container->setParameter('dian.validate_before_send', $config['validate_before_send']);
        $container->setParameter('dian.cert.path', $config['certificate']['path']);
        $container->setParameter('dian.cert.password', $config['certificate']['password']);
        $container->setParameter('dian.software.id', $config['software']['id'] ?? '');
        $container->setParameter('dian.software.pin', $config['software']['pin'] ?? '');
        $container->setParameter('dian.software.provider_nit', $config['software']['provider_nit'] ?? '');
        $container->setParameter('dian.software.provider_code', $config['software']['provider_code'] ?? '000');
        $container->setParameter('dian.nit', $config['nit']);
        $container->setParameter('dian.signer_role', $config['signer_role']);
        $container->setParameter('dian.report.logo_url', $config['report']['logo_url']);
        $container->setParameter('dian.report.accent_color', $config['report']['accent_color']);
    }
}
