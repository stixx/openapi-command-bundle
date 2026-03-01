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

use Stixx\OpenApiCommandBundle\Tests\Functional\App\Command\UpdateBookCommand;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Model\BookResource;

final class UpdateBookHandler
{
    public function __invoke(UpdateBookCommand $command): BookResource
    {
        // Simulate update and return updated resource
        return BookResource::create($command->id, $command->title, $command->author);
    }
}
