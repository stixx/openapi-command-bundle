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

namespace Stixx\OpenApiCommandBundle\Tests\Mock\Command;

use OpenApi\Attributes as OA;

#[OA\ExternalDocumentation(
    description: 'More info about the items domain',
    url: 'https://docs.example.com/items'
)]
#[OA\Parameter(
    name: 'X-Tenant',
    description: 'Tenant context header',
    in: 'header',
    required: false,
    schema: new OA\Schema(type: 'string')
)]
#[OA\Parameter(
    name: 'page',
    description: 'Page number (1-based)',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', minimum: 1)
)]
#[OA\RequestBody(
    description: 'Generic body used when applicable',
    required: true,
    content: new OA\JsonContent(
        required: ['name'],
        properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Foo'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
        ]
    )
)]
#[OA\Response(response: 400, description: 'Bad request')]
#[OA\Response(response: 401, description: 'Unauthorized')]
#[OA\Post(
    path: '/items',
    operationId: 'create_item_full',
    description: 'Creates a new item and returns it',
    summary: 'Create a new item',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'New name'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
            ]
        )
    ),
    tags: ['items', 'admin'],
    responses: [
        new OA\Response(
            response: 201,
            description: 'Created',
            headers: [
                new OA\Header(header: 'Location', description: 'Resource URI', schema: new OA\Schema(type: 'string')),
            ],
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                ]
            )
        ),
    ],
)]
final class CreateItemCommand
{
}
