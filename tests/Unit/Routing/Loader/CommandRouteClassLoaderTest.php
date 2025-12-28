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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;

use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;

/**
 * Tests for CommandRouteClassLoader.
 *
 * Note on class_exists guards:
 * We define small throwaway classes via eval() to simulate different
 * annotation/attribute scenarios. When PHPUnit runs tests in the same
 * process, re-defining the same class triggers a fatal error. We therefore
 * guard each eval() with class_exists() to avoid redeclaration and keep the
 * tests idempotent on re-runs.
 */
final class CommandRouteClassLoaderTest extends TestCase
{
    public function testReturnsEmptyForInvalidClass(): void
    {
        $loader = new CommandRouteClassLoader();

        $collection = $loader->load('');

        self::assertCount(0, $collection->all());
    }

    public function testReturnsEmptyForNonExistingClass(): void
    {
        $loader = new CommandRouteClassLoader();

        $collection = $loader->load('This\\Class\\DoesNotExist');

        self::assertCount(0, $collection->all());
    }

    public function testReturnsEmptyForAbstractClass(): void
    {
        // Avoid redeclaration in case this test runs multiple times in‑process
        if (!class_exists(__NAMESPACE__.'\\AbstractTmpCommand')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            abstract class AbstractTmpCommand { #[OA\Get(path: '/abstract')] public static function marker(){} }
            PHP);
        }

        $loader = new CommandRouteClassLoader();
        $collection = $loader->load(self::classNamespace('AbstractTmpCommand'));

        self::assertCount(0, $collection->all());
    }

    public function testReturnsEmptyWhenNoOperationAttributes(): void
    {
        if (!class_exists(__NAMESPACE__.'\\NoOpsCommand')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            final class NoOpsCommand {}
            PHP);
        }

        $loader = new CommandRouteClassLoader();
        $collection = $loader->load(self::classNamespace('NoOpsCommand'));

        self::assertCount(0, $collection->all());
    }

    public function testSkipsWhenClassIsInControllerClassesMap(): void
    {
        if (!class_exists(__NAMESPACE__.'\\MappedCommand')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            #[OA\Get(path: '/mapped')]
            final class MappedCommand {}
            PHP);
        }

        $fqcn = self::classNamespace('MappedCommand');
        $loader = new CommandRouteClassLoader(controllerClasses: [$fqcn => true]);
        $collection = $loader->load($fqcn);

        self::assertCount(0, $collection->all());
    }

    public function testBuildsRoutesForOperationsAndResolvesMethod(): void
    {
        if (!class_exists(__NAMESPACE__.'\\MultiOpsCommand')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            #[OA\Get(path: '/a')]
            #[OA\Post(path: '/b')]
            final class MultiOpsCommand {}
            PHP);
        }

        $loader = new CommandRouteClassLoader();
        $collection = $loader->load(self::classNamespace('MultiOpsCommand'));

        self::assertCount(2, $collection->all());
        $routes = $collection->all();
        $methods = array_values(array_map(fn ($r) => $r->getMethods(), $routes));

        // Flatten and sort for predictable assertion
        $flat = array_values(array_merge(...$methods));
        sort($flat);
        self::assertSame(['GET', 'POST'], $flat);
    }

    public function testRouteNameDefaultsAndEnsuresUniqueness(): void
    {
        if (!class_exists(__NAMESPACE__.'\\TwoGetNoIdCommand')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            #[OA\Get(path: '/one')]
            #[OA\Get(path: '/two')]
            final class TwoGetNoIdCommand {}
            PHP);
        }

        $fqcn = self::classNamespace('TwoGetNoIdCommand');
        $loader = new CommandRouteClassLoader();
        $collection = $loader->load($fqcn);

        $names = array_keys($collection->all());
        sort($names);

        // default name is command_{short_class}
        $base = 'command_twogetnoidcommand';
        self::assertSame([$base, $base.'_2'], $names);
    }

    public function testControllerResolutionOrder(): void
    {
        // 1) Operation vendor extension x[controller]
        if (!class_exists(__NAMESPACE__.'\\OpControllerCmd')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            #[OA\Get(path: '/x', x: ['controller' => \Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader\FakeControllerA::class])]
            final class OpControllerCmd {}
            PHP);
        }

        // 2) Class-level CommandObject(controller: ...)
        if (!class_exists(__NAMESPACE__.'\\ClassControllerCmd')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
            #[CommandObject(controller: \Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader\FakeControllerB::class)]
            #[OA\Get(path: '/y')]
            final class ClassControllerCmd {}
            PHP);
        }

        // 3) Default controller when none provided
        if (!class_exists(__NAMESPACE__.'\\DefaultControllerCmd')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            #[OA\Get(path: '/z')]
            final class DefaultControllerCmd {}
            PHP);
        }

        // Fake controllers for assertions
        if (!class_exists(__NAMESPACE__.'\\FakeControllerA')) {
            eval('namespace '.__NAMESPACE__.'; final class FakeControllerA {}');
        }
        if (!class_exists(__NAMESPACE__.'\\FakeControllerB')) {
            eval('namespace '.__NAMESPACE__.'; final class FakeControllerB {}');
        }

        $loader = new CommandRouteClassLoader();

        $opCtrlRoute = current($loader->load(self::classNamespace('OpControllerCmd'))->all());
        self::assertSame(self::classNamespace('FakeControllerA'), $opCtrlRoute->getDefault('_controller'));

        $classCtrlRoute = current($loader->load(self::classNamespace('ClassControllerCmd'))->all());
        self::assertSame(self::classNamespace('FakeControllerB'), $classCtrlRoute->getDefault('_controller'));

        $defaultRoute = current($loader->load(self::classNamespace('DefaultControllerCmd'))->all());
        self::assertSame(CommandController::class, $defaultRoute->getDefault('_controller'));
    }

    public function testOperationIdBecomesRouteName(): void
    {
        if (!class_exists(__NAMESPACE__.'\\WithOperationId')) {
            eval(<<<'PHP'
            namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;
            use OpenApi\Attributes as OA;
            #[OA\Get(path: '/oid', operationId: 'my_op')]
            final class WithOperationId {}
            PHP);
        }

        $loader = new CommandRouteClassLoader();
        $collection = $loader->load(self::classNamespace('WithOperationId'));
        $names = array_keys($collection->all());

        self::assertSame(['my_op'], $names);
    }

    private static function classNamespace(string $short): string
    {
        return __NAMESPACE__.'\\'.$short;
    }
}
