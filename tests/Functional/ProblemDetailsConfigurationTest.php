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

final class ProblemDetailsConfigurationTest extends AbstractKernelTestCase
{
    #[WithoutErrorHandler]
    public function testProblemDetailsAreLoadedByDefault(): void
    {
        // Arrange
        $this->bootKernel();
        $container = self::getContainer();

        /** @var ApiDocGenerator $generator */
        $generator = $container->get('nelmio_api_doc.generator.default');

        // Act
        /** @var array{components?: array{responses?: array<string, mixed>, schemas?: array<string, mixed>}} $spec */
        $spec = json_decode($generator->generate()->toJson(), true);

        // Assert
        $this->assertIsArray($spec);
        $this->assertArrayHasKey('components', $spec);
        $components = $spec['components'];
        $this->assertIsArray($components);

        $this->assertArrayHasKey('responses', $components);
        $responses = $components['responses'];
        $this->assertIsArray($responses);

        $this->assertArrayHasKey('InvalidRequestProblemDetailsResponse', $responses);
        $this->assertArrayHasKey('DefaultProblemDetailsResponse', $responses);

        $this->assertArrayHasKey('schemas', $components);
        $schemas = $components['schemas'];
        $this->assertIsArray($schemas);
        // These schemas come from the attributes in the Model classes
        $this->assertArrayHasKey('ProblemDetails', $schemas);
        $this->assertArrayHasKey('ProblemDetailsInvalidRequestBody', $schemas);
    }

    #[WithoutErrorHandler]
    public function testProblemDetailsCanBeDisabled(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (App\Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/disable_problem_details.php');
        });
        $container = $kernel->getContainer();

        /** @var ApiDocGenerator $generator */
        $generator = $container->get('nelmio_api_doc.generator.default');

        $prevErrorHandler = set_error_handler(static fn () => true);
        // Act
        /** @var array{components?: array{responses?: array<string, mixed>}} $spec */
        $spec = json_decode($generator->generate()->toJson(), true);
        set_error_handler($prevErrorHandler);

        // Assert
        $this->assertIsArray($spec);
        if (isset($spec['components']['responses'])) {
            $this->assertArrayNotHasKey('InvalidRequestProblemDetailsResponse', $spec['components']['responses']);
        }
    }

    #[WithoutErrorHandler]
    public function testProblemDetailsCanBeOverriddenByProjectConfig(): void
    {
        // Arrange
        $kernel = $this->createKernelWithConfig(static function (App\Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/override_nelmio.php');
        });
        $container = $kernel->getContainer();

        /** @var ApiDocGenerator $generator */
        $generator = $container->get('nelmio_api_doc.generator.default');

        $prevErrorHandler = set_error_handler(static fn () => true);
        // Act
        /** @var array{components?: array{responses?: array<string, array{description?: string}>}} $spec */
        $spec = json_decode($generator->generate()->toJson(), true);
        set_error_handler($prevErrorHandler);

        // Assert
        $this->assertIsArray($spec);
        $this->assertArrayHasKey('components', $spec);
        $components = $spec['components'];
        $this->assertIsArray($components);

        $this->assertArrayHasKey('responses', $components);
        $responses = $components['responses'];
        $this->assertIsArray($responses);

        $this->assertArrayHasKey('InvalidRequestProblemDetailsResponse', $responses);
        $response = $responses['InvalidRequestProblemDetailsResponse'];
        $this->assertIsArray($response);

        $this->assertArrayHasKey('description', $response);
        $this->assertEquals(
            'Overridden RFC7807 Problem Details',
            $response['description']
        );

        $this->assertArrayNotHasKey('content', $response);
    }
}
