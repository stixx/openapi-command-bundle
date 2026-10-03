<?php

declare(strict_types=1);

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stixx\OpenApiCommandBundle\Tests\Functional\App;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A {@see DiscoveryKernel} that counts route loads and keeps its cache in a given directory, so a second instance
 * starts from the first one's warm cache, as the next request would. Don't shut the first one down: TestKernel
 * deletes its cache directory on shutdown.
 */
class RouteLoadCountingKernel extends DiscoveryKernel
{
    public function __construct(string $environment, bool $debug, private readonly string $fixedCacheDir)
    {
        parent::__construct($environment, $debug);
    }

    public function getCacheDir(): string
    {
        return $this->fixedCacheDir;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->register(CountingRouteLoader::class, CountingRouteLoader::class)
            ->setDecoratedService('routing.loader', null, -10)
            ->setArguments([new Reference('.inner')]);
    }
}
