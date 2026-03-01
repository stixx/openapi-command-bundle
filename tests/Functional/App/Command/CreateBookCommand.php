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
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Model\BookRequest;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Model\BookResource;

#[OA\Post(
    path: '/api/books',
    summary: 'Create a book',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: BookRequest::class))
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Book created',
            content: new OA\JsonContent(ref: new Model(type: BookResource::class))
        ),
        new OA\Response(ref: '#/components/responses/InvalidRequestProblemDetailsResponse', response: 400),
        new OA\Response(ref: '#/components/responses/DefaultProblemDetailsResponse', response: 500),
    ]
)]
final class CreateBookCommand extends BookRequest
{
}
