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

namespace Stixx\OpenApiCommandBundle\Validator;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;

final readonly class RequestValidatorChain implements ValidatorInterface
{
    /**
     * @param iterable<ValidatorInterface> $validators
     */
    public function __construct(
        #[TaggedIterator(ValidatorInterface::TAG_NAME)]
        private iterable $validators
    ) {
    }

    public function validate(Request $request): void
    {
        foreach ($this->validators as $validator) {
            $validator->validate($request);
        }
    }
}
