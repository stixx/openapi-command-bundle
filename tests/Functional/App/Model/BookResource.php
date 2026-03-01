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

namespace Stixx\OpenApiCommandBundle\Tests\Functional\App\Model;

use OpenApi\Attributes as OA;

#[OA\Schema(title: 'BookResource', description: 'A book resource')]
final readonly class BookResource
{
    public function __construct(
        #[OA\Property(description: 'The unique identifier of the book')]
        public string $id,
        #[OA\Property(description: 'The title of the book')]
        public string $title,
        #[OA\Property(description: 'The author of the book')]
        public ?string $author = null,
    ) {
    }

    public static function create(string $id, string $title, ?string $author = null): self
    {
        return new self($id, $title, $author);
    }
}
