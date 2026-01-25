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

use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ConstraintViolationListNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ConstraintViolationListInterface;
    }

    /**
     * @param ConstraintViolationListInterface $data
     * @param array<string, mixed> $context
     *
     * @return array<int, array<string, mixed>>
     */
    public function normalize($data, ?string $format = null, array $context = []): array
    {
        $out = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($data as $violation) {
            $normalized = $this->normalizer->normalize($violation, $format, $context);
            if (is_array($normalized)) {
                /* @var array<string, mixed> $normalized */
                $out[] = $normalized;
            }
        }

        /** @var array<int, array<string, mixed>> $out */
        return $out;
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ConstraintViolationListInterface::class => true];
    }
}
