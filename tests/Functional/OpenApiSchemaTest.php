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

use Nelmio\ApiDocBundle\ApiDocGenerator;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;

final class OpenApiSchemaTest extends AbstractKernelTestCase
{
    #[WithoutErrorHandler]
    public function testOpenApiSchemaOutput(): void
    {
        // Arrange
        $container = self::getContainer();

        /** @var ApiDocGenerator $generator */
        $generator = $container->get('nelmio_api_doc.generator.default');

        // Act
        $openApi = $generator->generate();
        $json = $openApi->toJson();

        // Assert
        $this->assertJsonStringEqualsJsonFile(__DIR__.'/Resources/specifications/openapi.json', $json);
    }
}
