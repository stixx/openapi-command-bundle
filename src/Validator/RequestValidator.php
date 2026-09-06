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
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class RequestValidator implements ValidatorInterface
{
    /** @var array<string, OpenApiRequestValidator> */
    private array $cachedValidators = [];

    /**
     * @param ServiceLocator<ApiDocGenerator>|null $generatorsLocator
     */
    public function __construct(
        private readonly ApiDocGenerator $apiDocGenerator,
        private readonly HttpMessageFactoryInterface $psrHttpFactory,
        private readonly ?ServiceLocator $generatorsLocator = null,
        private readonly ?NelmioAreaRoutesChecker $areaRoutesChecker = null,
    ) {
    }

    public function validate(Request $request): void
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $this->getValidator($this->areaFor($request))->validate($psrRequest);
    }

    private function areaFor(Request $request): string
    {
        return $this->areaRoutesChecker?->areaFor($request) ?? 'default';
    }

    private function getValidator(string $area): OpenApiRequestValidator
    {
        if (isset($this->cachedValidators[$area])) {
            return $this->cachedValidators[$area];
        }

        $generator = $this->generatorsLocator?->has($area) === true
            ? $this->generatorsLocator->get($area)
            : $this->apiDocGenerator;

        $apiDoc = $generator->generate();

        return $this->cachedValidators[$area] = new ValidatorBuilder()
            ->fromJson($apiDoc->toJson())
            ->getRequestValidator();
    }
}
