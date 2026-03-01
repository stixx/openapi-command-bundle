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

use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Nyholm\BundleTest\TestKernel;
use Stixx\OpenApiCommandBundle\StixxOpenApiCommandBundle;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Command\{CreateBookCommand, DeleteBookCommand, UpdateBookCommand};
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends TestKernel
{
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        $this->addTestBundle(FrameworkBundle::class);
        $this->addTestBundle(NelmioApiDocBundle::class);
        $this->addTestBundle(StixxOpenApiCommandBundle::class);
        $this->addTestConfig(__DIR__.'/../Resources/config/default.php');
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/../';
    }

    /**
     * @param RoutingConfigurator $routes
     */
    protected function configureRoutes($routes): void
    {
        parent::configureRoutes($routes);

        if ($routes instanceof RoutingConfigurator) {
            $routes->import(CreateBookCommand::class, 'attribute');
            $routes->import(UpdateBookCommand::class, 'attribute');
            $routes->import(DeleteBookCommand::class, 'attribute');
        }
    }
}
