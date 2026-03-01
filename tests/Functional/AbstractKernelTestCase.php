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

namespace Stixx\OpenApiCommandBundle\Tests\Functional;

use Stixx\OpenApiCommandBundle\Tests\Functional\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AbstractKernelTestCase extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        for ($i = 0; $i < 5; ++$i) {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function createKernelWithConfig(callable $config): Kernel
    {
        $kernel = new Kernel('test', true);

        $config($kernel);
        $kernel->boot();

        return $kernel;
    }
}
