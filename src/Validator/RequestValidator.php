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
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class RequestValidator implements RequestValidatorInterface
{
    /** @var array<string, array{string, OpenApiRequestValidator}> */
    private array $validators = [];

    public function __construct(
        private readonly OpenApiSpecCache $specCache,
        private readonly HttpMessageFactoryInterface $psrHttpFactory,
        private readonly ?NelmioAreaRoutesChecker $areaRoutesChecker = null,
    ) {
    }

    public function validate(Request $request): void
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $this->validatorFor($this->areaFor($request))->validate($psrRequest);
    }

    private function areaFor(Request $request): string
    {
        return $this->areaRoutesChecker?->areaFor($request) ?? 'default';
    }

    private function validatorFor(string $area): OpenApiRequestValidator
    {
        $json = $this->specCache->jsonFor($area);

        if (($this->validators[$area][0] ?? null) !== $json) {
            $this->validators[$area] = [$json, new ValidatorBuilder()->fromJson($json)->getRequestValidator()];
        }

        return $this->validators[$area][1];
    }
}
