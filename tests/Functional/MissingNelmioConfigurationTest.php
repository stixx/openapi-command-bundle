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

use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\MissingNelmioKernel;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * The unit tests call the compiler pass directly, which cannot show whether the exception survives a real
 * container build — the bundle's own prepend() also writes nelmio_api_doc config, so something upstream
 * could fail first with a different error and the pass would never run.
 */
final class MissingNelmioConfigurationTest extends AbstractKernelTestCase
{
    #[WithoutErrorHandler]
    public function testBootingWithoutNelmioApiDocBundleExplainsHowToFixIt(): void
    {
        // Arrange
        $kernel = new MissingNelmioKernel('test', true);

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('NelmioApiDocBundle is not registered in config/bundles.php');

        // Act
        $kernel->boot();
    }
}
