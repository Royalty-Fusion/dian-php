<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Bridge\Symfony;

use RoyaltyFusion\DianPhp\Bridge\Symfony\DependencyInjection\DianExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Royalty Fusion DIAN bundle.
 *
 * Wire it into a Symfony application by adding it to bundles.php:
 *
 *     RoyaltyFusion\DianPhp\Bridge\Symfony\DianBundle::class => ['all' => true],
 *
 * Then configure via config/packages/dian.yaml — see Configuration.php for
 * the full schema.
 */
class DianBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new DianExtension();
    }
}
