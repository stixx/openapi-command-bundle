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

use Nyholm\BundleTest\TestKernel;
use Stixx\OpenApiCommandBundle\StixxOpenApiCommandBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

/**
 * A kernel without NelmioApiDocBundle, reproducing a skipped Flex recipe.
 */
class MissingNelmioKernel extends TestKernel
{
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        $this->addTestBundle(FrameworkBundle::class);
        // NelmioApiDocBundle is deliberately not registered.
        $this->addTestBundle(StixxOpenApiCommandBundle::class);
        $this->addTestConfig(__DIR__.'/../Resources/config/without_nelmio.php');
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/../';
    }
}
