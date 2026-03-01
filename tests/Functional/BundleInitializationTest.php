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
use Stixx\OpenApiCommandBundle\Controller\ArgumentResolver\CommandValueResolver;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Stixx\OpenApiCommandBundle\EventSubscriber\{ApiExceptionSubscriber, RequestValidatorSubscriber};
use Stixx\OpenApiCommandBundle\Exception\{DefaultExceptionToApiProblemTransformer, ExceptionToApiProblemTransformerInterface};
use Stixx\OpenApiCommandBundle\Responder\{JsonResponder, JsonSerializedResponder, NullableResponder, ResponderChain, ResponderInterface};
use Stixx\OpenApiCommandBundle\Response\{ResponseStatusResolver, StatusResolverInterface};
use Stixx\OpenApiCommandBundle\RouteDescriber\CommandRouteDescriber;
use Stixx\OpenApiCommandBundle\Routing\Loader\{AttributeDirectoryLoaderDecorator, CommandRouteClassLoader};
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\{ApiProblemNormalizer, ConstraintViolationListNormalizer, ConstraintViolationNormalizer};
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Kernel;
use Stixx\OpenApiCommandBundle\Validator\{RequestValidator, RequestValidatorChain, ValidatorInterface as StixxValidatorInterface};
use Symfony\Component\Serializer\Serializer;

final class BundleInitializationTest extends AbstractKernelTestCase
{
    #[WithoutErrorHandler]
    public function testInitBundle(): void
    {
        // Arrange
        $this->bootKernel();
        $container = self::getContainer();

        // Assert
        $this->assertTrue($container->hasParameter('stixx_openapi_command.validation.enabled'));
        $this->assertTrue($container->hasParameter('stixx_openapi_command.validation.groups'));

        // Controllers & Resolvers
        $this->assertTrue($container->has(CommandController::class));
        $this->assertTrue($container->has(CommandValueResolver::class));

        // Subscribers
        $this->assertTrue($container->has(ApiExceptionSubscriber::class));
        $this->assertTrue($container->has(RequestValidatorSubscriber::class));

        // Exceptions
        $this->assertTrue($container->has(DefaultExceptionToApiProblemTransformer::class));
        $this->assertTrue($container->has(ExceptionToApiProblemTransformerInterface::class));

        // Responders
        $this->assertTrue($container->has(ResponderChain::class));
        $this->assertTrue($container->has(ResponderInterface::class));
        $this->assertTrue($container->has(JsonResponder::class));
        $this->assertTrue($container->has(JsonSerializedResponder::class));
        $this->assertTrue($container->has(NullableResponder::class));

        // Response Status
        $this->assertTrue($container->has(ResponseStatusResolver::class));
        $this->assertTrue($container->has(StatusResolverInterface::class));

        // OpenAPI
        $this->assertTrue($container->has(CommandRouteDescriber::class));

        // Routing
        $this->assertTrue($container->has(NelmioAreaRoutesChecker::class));
        $this->assertTrue($container->has(CommandRouteClassLoader::class));
        $this->assertTrue($container->has(AttributeDirectoryLoaderDecorator::class));

        // Validators
        $this->assertTrue($container->has(RequestValidatorChain::class));
        $this->assertTrue($container->has(StixxValidatorInterface::class));
        $this->assertTrue($container->has(RequestValidator::class));

        // Serializer
        $this->assertTrue($container->has(ApiProblemNormalizer::class));
        $this->assertTrue($container->has(ConstraintViolationNormalizer::class));
        $this->assertTrue($container->has(ConstraintViolationListNormalizer::class));
        $this->assertTrue($container->has('stixx_openapi_command.problem_serializer'));
        $this->assertInstanceOf(Serializer::class, $container->get('stixx_openapi_command.problem_serializer'));
    }

    #[WithoutErrorHandler]
    public function testBundleWithCustomConfiguration(): void
    {
        $kernel = $this->createKernelWithConfig(static function (Kernel $kernel): void {
            $kernel->addTestConfig(__DIR__.'/Resources/config/custom.php');
        });
        $container = $kernel->getContainer();

        // Act
        $enabled = $container->getParameter('stixx_openapi_command.validation.enabled');
        $groups = $container->getParameter('stixx_openapi_command.validation.groups');

        // Assert
        $this->assertFalse($enabled);
        $this->assertSame(['Custom'], $groups);
    }
}
