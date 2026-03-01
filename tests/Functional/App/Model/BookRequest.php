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
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'BookRequest', description: 'Request payload for a book')]
class BookRequest
{
    #[Assert\NotBlank]
    #[OA\Property(description: 'The title of the book')]
    public string $title;

    #[Assert\Length(min: 1, max: 100)]
    #[OA\Property(description: 'The author of the book')]
    public ?string $author = null;
}
