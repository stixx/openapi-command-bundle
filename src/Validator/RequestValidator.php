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

use League\OpenAPIValidation\PSR7\RequestValidator as OpenApiRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class RequestValidator implements ValidatorInterface
{
    private ?OpenApiRequestValidator $cachedValidator = null;

    public function __construct(
        private readonly ApiDocGenerator $apiDocGenerator,
        private readonly HttpMessageFactoryInterface $psrHttpFactory,
    ) {
    }

    public function validate(Request $request): void
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $this->getValidator()->validate($psrRequest);
    }

    private function getValidator(): OpenApiRequestValidator
    {
        if ($this->cachedValidator !== null) {
            return $this->cachedValidator;
        }

        $apiDoc = $this->apiDocGenerator->generate();

        return $this->cachedValidator = new ValidatorBuilder()->fromJson($apiDoc->toJson())->getRequestValidator();
    }
}
