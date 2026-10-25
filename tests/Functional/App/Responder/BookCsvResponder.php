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

namespace Stixx\OpenApiCommandBundle\Tests\Functional\App\Responder;

use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Responder\ResponseContext;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Model\BookResource;
use Symfony\Component\HttpFoundation\Response;

final class BookCsvResponder implements ResponderInterface
{
    public function supports(ResponseContext $context): bool
    {
        return $context->getResult() instanceof BookResource
            && $context->getRequest()->headers->get('Accept') === 'text/csv';
    }

    public function respond(ResponseContext $context, int $status): Response
    {
        /** @var BookResource $book */
        $book = $context->getResult();

        return new Response(sprintf("id,title\n%s,%s\n", $book->id, $book->title), $status, ['Content-Type' => 'text/csv']);
    }
}
