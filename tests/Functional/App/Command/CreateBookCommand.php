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

namespace Stixx\OpenApiCommandBundle\Tests\Functional\App\Command;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Model\BookResource;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Post(
    path: '/api/books',
    summary: 'Create a book',
    responses: [
        new OA\Response(
            response: 201,
            description: 'Book created',
            content: new OA\JsonContent(ref: new Model(type: BookResource::class))
        ),
    ]
)]
final class CreateBookCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public string $title,
        #[Assert\Length(min: 1, max: 100)]
        public ?string $author = null,
    ) {
    }
}
