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

namespace Stixx\OpenApiCommandBundle\Serializer\Normalizer;

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

final class ConstraintViolationNormalizer implements NormalizerInterface
{
    /**
     * @var array<class-string, array<string,string>>
     */
    private array $constMapCache = [];

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ConstraintViolationInterface;
    }

    /**
     * @param ConstraintViolationInterface $data
     *
     * @return array{propertyPath:?string,message:string,code:?string,constraint:?string,error:?string}
     */
    public function normalize($data, ?string $format = null, array $context = []): array
    {
        $violation = $data;

        $code = $violation->getCode();
        $constraint = $violation->getConstraint();

        $constraintName = null;
        $errorName = null;

        if ($constraint !== null) {
            $reflectionClass = new ReflectionClass($constraint);
            $constraintName = $reflectionClass->getShortName();

            if ($code !== null) {
                $map = $this->constMapCache[$reflectionClass->getName()] ??= $this->buildConstantMap($reflectionClass);
                $errorName = $map[$code] ?? null;
            }
        }

        return [
            'propertyPath' => $violation->getPropertyPath() ?: null,
            'message' => $violation->getMessage(),
            'code' => $code,
            'constraint' => $constraintName,
            'error' => $errorName,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ConstraintViolationInterface::class => true];
    }

    /**
     * @return array<string,string>
     */
    private function buildConstantMap(ReflectionClass $reflectionClass): array
    {
        $map = [];
        foreach ($reflectionClass->getConstants() as $name => $value) {
            if (is_string($value)) {
                $map[$value] = $name;
            }
        }

        return $map;
    }
}
