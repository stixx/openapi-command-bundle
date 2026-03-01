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

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Delete(path: '/api/books/{id}', summary: 'Delete a book')]
#[OA\Response(response: 204, description: 'Book deleted')]
#[OA\Response(ref: '#/components/responses/ResourceNotFoundProblemDetailsResponse', response: 404)]
#[OA\Response(ref: '#/components/responses/DefaultProblemDetailsResponse', response: 500)]
final class DeleteBookCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Parameter(name: 'id', description: 'The book ID', in: 'path', required: true)]
        public string $id,
    ) {
    }
}
