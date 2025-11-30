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

use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

final readonly class RequestValidator implements ValidatorInterface
{
    public function __construct(
        private ApiDocGenerator $apiDocGenerator,
    ) {
    }

    public function validate(Request $request): void
    {
        $apiDoc = $this->apiDocGenerator->generate();

        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $psrHttpFactory->createRequest($request);

        $validatorBuilder = new ValidatorBuilder()->fromJson($apiDoc->toJson())->getRequestValidator();
        $validatorBuilder->validate($psrRequest);
    }
}
