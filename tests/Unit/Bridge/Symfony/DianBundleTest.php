<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Bridge\Symfony\DependencyInjection\Configuration;
use RoyaltyFusion\DianPhp\Bridge\Symfony\DependencyInjection\DianExtension;
use RoyaltyFusion\DianPhp\Dian;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DianBundleTest extends TestCase
{
    public function testConfigurationAcceptsCanonicalShape(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            [
                'environment'         => 'habilitacion',
                'certificate'         => ['path' => '/tmp/test.p12', 'password' => 's3cr3t'],
                'software'            => ['id' => 'uuid-1', 'pin' => '00000', 'provider_nit' => '900123456'],
                'report'              => ['logo_url' => '/img.png', 'accent_color' => '#000'],
                'validate_before_send' => true,
            ],
        ]);

        $this->assertSame('habilitacion', $config['environment']);
        $this->assertSame('/tmp/test.p12', $config['certificate']['path']);
        $this->assertSame('#000', $config['report']['accent_color']);
    }

    public function testExtensionRegistersDianServiceAsPublic(): void
    {
        $container = new ContainerBuilder();
        (new DianExtension())->load([
            [
                'environment' => 'habilitacion',
                'certificate' => ['path' => '/tmp/test.p12', 'password' => 's3cr3t'],
            ],
        ], $container);

        $this->assertTrue($container->has(Dian::class));
        $definition = $container->getDefinition(Dian::class);
        $this->assertTrue($definition->isPublic());
        $this->assertSame('/tmp/test.p12', $container->getParameter('dian.cert.path'));
        $this->assertSame('habilitacion', $container->getParameter('dian.environment'));
    }
}
