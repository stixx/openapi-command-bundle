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

namespace Stixx\OpenApiCommandBundle\Tests\Functional\App\Handler;

use Stixx\OpenApiCommandBundle\Tests\Functional\App\Command\CreateBookCommand;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Model\BookResource;

final class CreateBookHandler
{
    public function __invoke(CreateBookCommand $command): BookResource
    {
        // Simulate persistence and return created resource representation
        return BookResource::create('1', $command->title, $command->author);
    }
}
