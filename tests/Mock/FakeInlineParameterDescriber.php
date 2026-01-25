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

namespace Stixx\OpenApiCommandBundle\Tests\Mock;

use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\RouteArgumentDescriberInterface;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class FakeInlineParameterDescriber implements RouteArgumentDescriberInterface
{
    /**
     * @var list<array{ArgumentMetadata, OA\Operation}>
     */
    public array $calls = [];

    public function describe(ArgumentMetadata $argumentMetadata, OA\Operation $operation): void
    {
        $this->calls[] = [$argumentMetadata, $operation];
    }
}
