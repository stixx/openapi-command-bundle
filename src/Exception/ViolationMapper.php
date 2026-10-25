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

namespace Stixx\OpenApiCommandBundle\Exception;

use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidParameter;
use League\OpenAPIValidation\PSR7\Exception\Validation\RequiredParameterMissing;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use ReflectionClass;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

/**
 * @internal
 */
final class ViolationMapper
{
    public const string OPENAPI_CONSTRAINT = 'openapi_request_validation';

    /**
     * @return list<Violation>
     */
    public static function fromConstraintViolations(ConstraintViolationListInterface $violations): array
    {
        $mapped = [];
        foreach ($violations as $violation) {
            $code = $violation->getCode();
            $constraint = $violation->getConstraint();

            $mapped[] = new Violation(
                propertyPath: $violation->getPropertyPath(),
                message: (string) $violation->getMessage(),
                code: $code,
                constraint: $constraint === null ? null : new ReflectionClass($constraint)->getShortName(),
                error: $constraint === null || $code === null ? null : self::errorName($constraint, $code),
            );
        }

        return $mapped;
    }

    public static function fromOpenApiValidation(Throwable $failure): Violation
    {
        $parameter = null;
        $schemaMismatch = null;
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof InvalidParameter || $cause instanceof RequiredParameterMissing) {
                $parameter ??= $cause->name();
            }
            if ($cause instanceof SchemaMismatch) {
                $schemaMismatch ??= $cause;
            }
        }

        $path = [];
        foreach ($schemaMismatch?->dataBreadCrumb()?->buildChain() ?? [] as $key) {
            if (is_int($key) || (is_string($key) && $key !== '')) {
                $path[] = (string) $key;
            }
        }

        return new Violation(
            propertyPath: $path === [] ? $parameter ?? '' : implode('.', $path),
            message: $schemaMismatch?->getMessage() ?: $failure->getPrevious()?->getMessage() ?: $failure->getMessage(),
            code: $schemaMismatch instanceof KeywordMismatch ? $schemaMismatch->keyword() : null,
            constraint: self::OPENAPI_CONSTRAINT,
        );
    }

    private static function errorName(Constraint $constraint, string $code): ?string
    {
        foreach (new ReflectionClass($constraint)->getConstants() as $name => $value) {
            if ($value === $code) {
                return $name;
            }
        }

        return null;
    }
}
