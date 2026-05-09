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

namespace Stixx\OpenApiCommandBundle\Exception;

use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Throwable;

final readonly class WrappedExceptionUnwrapper
{
    public function unwrap(Throwable $throwable): Throwable
    {
        if (!$throwable instanceof WrappedExceptionsInterface) {
            return $throwable;
        }

        $wrapped = $throwable->getWrappedExceptions(recursive: true);
        $first = current($wrapped);

        return $first instanceof Throwable ? $first : $throwable;
    }
}
